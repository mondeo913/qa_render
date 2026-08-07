<?php

namespace App\Services;

use App\Enums\DeliverableStatus;
use App\Enums\RoleCode;
use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class LoadBoardService
{
    public const COLUMN_TODO = 'todo';
    public const COLUMN_PROGRESS = 'progress';
    public const COLUMN_REVIEW = 'review';
    public const COLUMN_DONE = 'done';

    public function __construct(
        private readonly AccessScopeService $access
    ) {}

    /**
     * @param array{
     *   agency_id?: int|null,
     *   unit_id?: int|null,
     *   period?: string|null,
     *   q?: string|null,
     *   mine?: bool|null
     * } $filters
     */
    public function forUser(User $user, array $filters = []): array
    {
        $normalized = [
            'agency_id' => isset($filters['agency_id']) ? (int) $filters['agency_id'] : null,
            'unit_id' => isset($filters['unit_id']) ? (int) $filters['unit_id'] : null,
            'period' => trim((string) ($filters['period'] ?? '')) ?: null,
            'q' => trim((string) ($filters['q'] ?? '')) ?: null,
            'mine' => (bool) ($filters['mine'] ?? false),
        ];

        $availableAgencies = $this->availableAgencies($user);
        $availableUnits = $this->availableUnits($user);

        if ($normalized['agency_id'] !== null
            && !$availableAgencies->contains('id', $normalized['agency_id'])) {
            $normalized['agency_id'] = null;
        }

        if ($normalized['unit_id'] !== null
            && !$availableUnits->contains('id', $normalized['unit_id'])) {
            $normalized['unit_id'] = null;
        }

        $catalogQuery = $this->baseQuery($user, $normalized, includeAgencyFilter: false);
        $boardQuery = $this->baseQuery($user, $normalized, includeAgencyFilter: true);

        $catalogLoads = $catalogQuery->get();
        $boardLoads = $boardQuery
            ->orderByRaw('CASE WHEN effective_close_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('effective_close_at')
            ->orderByDesc('priority')
            ->get();

        $this->decorateLoads($catalogLoads);
        $this->decorateLoads($boardLoads);

        $columns = collect($this->columnDefinitions())
            ->mapWithKeys(fn (array $definition, string $key) => [
                $key => $definition + [
                    'loads' => $boardLoads
                        ->where('board_column', $key)
                        ->values(),
                ],
            ]);

        return [
            'filters' => $normalized,
            'columns' => $columns,
            'summary' => $this->summarize($boardLoads),
            'dependencyCards' => $this->dependencyCards($catalogLoads),
            'agencies' => $availableAgencies,
            'units' => $availableUnits,
            'periods' => $this->availablePeriods($user),
            'scopeLabel' => $this->scopeLabel($user, $normalized['unit_id'], $availableUnits),
            'canUseMineFilter' => RoleCode::isOperator($user->role?->code),
        ];
    }

    /**
     * Translate a technical SIGET status into one of the four visual columns.
     */
    public static function columnForStatus(string $status, float $completion = 0): string
    {
        $status = strtoupper($status);

        return match ($status) {
            'VALIDADA', 'VALIDADO_Y_CERRADO' => self::COLUMN_DONE,
            'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'REPROGRAMADA_ENTREGADA',
            'LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO' => self::COLUMN_REVIEW,
            'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA', 'OBSERVADA', 'REABIERTA' => self::COLUMN_PROGRESS,
            'VENCIDA' => $completion > 0 ? self::COLUMN_PROGRESS : self::COLUMN_TODO,
            default => self::COLUMN_TODO,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'PROGRAMADA' => 'Programada',
            'ABIERTA' => 'Ventana abierta',
            'EN_CAPTURA' => 'En captura',
            'PARCIALMENTE_ENTREGADA' => 'Entrega parcial',
            'ENTREGADA' => 'Entregada',
            'EN_REVISION_INSTITUCIONAL' => 'Revisión institucional',
            'OBSERVADA' => 'Con observaciones',
            'LISTA_PARA_FIRMA' => 'Lista para firma',
            'PENDIENTE_DOCUMENTO_FIRMADO' => 'Pendiente de documento firmado',
            'VALIDADA' => 'Validada',
            'VALIDADO_Y_CERRADO' => 'Validada y cerrada',
            'SUSPENDIDA' => 'Suspendida',
            'REPROGRAMADA' => 'Reprogramada',
            'REPROGRAMADA_ABIERTA' => 'Reprogramada abierta',
            'REPROGRAMADA_ENTREGADA' => 'Reprogramada entregada',
            'VENCIDA' => 'Vencida',
            'CANCELADA' => 'Cancelada',
            'REABIERTA' => 'Reabierta',
            default => str($status)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    private function baseQuery(User $user, array $filters, bool $includeAgencyFilter): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);

        $query->with([
            'agency:id,code,name,metadata',
            'deliverables' => function (Builder $deliverables) use ($user, $filters) {
                $this->access->scopeDeliverables($deliverables, $user);

                if ($filters['unit_id']) {
                    $deliverables->where('organizational_unit_id', $filters['unit_id']);
                }

                if ($filters['mine'] && RoleCode::isOperator($user->role?->code)) {
                    $deliverables->where('responsible_user_id', $user->id);
                }

                $deliverables->with([
                    'organizationalUnit:id,code,name',
                    'responsibleUser:id,name',
                    'evidences:id,deliverable_id,status',
                ]);
            },
        ]);

        if ($includeAgencyFilter && $filters['agency_id']) {
            $query->where('contracting_agency_id', $filters['agency_id']);
        }

        if ($filters['unit_id']) {
            $query->whereHas('deliverables', fn (Builder $deliverables) =>
                $deliverables->where('organizational_unit_id', $filters['unit_id'])
            );
        }

        if ($filters['mine'] && RoleCode::isOperator($user->role?->code)) {
            $query->whereHas('deliverables', fn (Builder $deliverables) =>
                $deliverables->where('responsible_user_id', $user->id)
            );
        }

        if ($filters['period']) {
            $query->where('period_label', $filters['period']);
        }

        if ($filters['q']) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $query->where(function (Builder $search) use ($term) {
                $search
                    ->where('title', 'like', $term)
                    ->orWhere('period_label', 'like', $term)
                    ->orWhereHas('agency', fn (Builder $agency) =>
                        $agency->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term)
                    );
            });
        }

        return $query;
    }

    private function availableAgencies(User $user): Collection
    {
        return ContractingAgency::query()
            ->select(['id', 'code', 'name', 'metadata'])
            ->where('active', true)
            ->whereHas('scheduledLoads', function (Builder $loads) use ($user) {
                $this->access->scopeLoads($loads, $user);
            })
            ->orderBy('name')
            ->get();
    }

    private function availableUnits(User $user): Collection
    {
        $unitIds = $this->access->accessibleUnitIds($user);

        $query = OrganizationalUnit::query()
            ->select(['id', 'contracting_agency_id', 'code', 'name'])
            ->where('active', true);

        if ($unitIds !== []) {
            $query->whereIn('id', $unitIds);
        } else {
            $query->whereHas('deliverables.scheduledLoad', function (Builder $loads) use ($user) {
                $this->access->scopeLoads($loads, $user);
            });
        }

        return $query->orderBy('name')->get();
    }

    private function availablePeriods(User $user): Collection
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);

        return $query
            ->whereNotNull('period_label')
            ->select('period_label')
            ->distinct()
            ->orderByDesc('period_label')
            ->pluck('period_label');
    }

    private function decorateLoads(Collection $loads): void
    {
        foreach ($loads as $load) {
            $progress = $this->visibleProgress($load);
            $status = $load->status instanceof \BackedEnum
                ? $load->status->value
                : (string) $load->status;

            $load->setAttribute('board_progress', $progress);
            $load->setAttribute('board_column', self::columnForStatus($status, $progress));
            $load->setAttribute('board_status_label', self::statusLabel($status));
            $load->setAttribute('board_overdue', $this->isOverdue($load, $status));
            $load->setAttribute('board_unit_names', $load->deliverables
                ->pluck('organizationalUnit.name')
                ->filter()
                ->unique()
                ->values());
            $load->setAttribute('board_responsibles', $load->deliverables
                ->pluck('responsibleUser.name')
                ->filter()
                ->unique()
                ->values());
            $load->setAttribute('board_evidence_count', $load->deliverables
                ->sum(fn ($deliverable) => $deliverable->evidences->count()));
            $load->setAttribute('board_observation_count', $load->deliverables
                ->filter(fn ($deliverable) => trim((string) $deliverable->observations) !== '')
                ->count());
        }
    }

    private function visibleProgress(ScheduledLoad $load): float
    {
        if ($load->deliverables->isEmpty()) {
            return (float) ($load->completion_percentage ?? 0);
        }

        $weights = [
            DeliverableStatus::PENDIENTE->value => 0,
            DeliverableStatus::EN_CAPTURA->value => 20,
            DeliverableStatus::ENVIADO->value => 45,
            DeliverableStatus::EN_REVISION->value => 60,
            DeliverableStatus::OBSERVADO->value => 45,
            DeliverableStatus::CORREGIDO->value => 65,
            DeliverableStatus::VALIDADO->value => 90,
            DeliverableStatus::CERRADO->value => 100,
        ];

        return round($load->deliverables->avg(function ($deliverable) use ($weights) {
            $status = $deliverable->status instanceof \BackedEnum
                ? $deliverable->status->value
                : (string) $deliverable->status;

            return $weights[$status] ?? 0;
        }), 2);
    }

    private function isOverdue(ScheduledLoad $load, string $status): bool
    {
        if (in_array($status, ['VALIDADA', 'VALIDADO_Y_CERRADO', 'CANCELADA'], true)) {
            return false;
        }

        return $status === 'VENCIDA'
            || ($load->effective_close_at && $load->effective_close_at->isPast());
    }

    private function summarize(Collection $loads): array
    {
        return [
            'total' => $loads->count(),
            'todo' => $loads->where('board_column', self::COLUMN_TODO)->count(),
            'progress' => $loads->where('board_column', self::COLUMN_PROGRESS)->count(),
            'review' => $loads->where('board_column', self::COLUMN_REVIEW)->count(),
            'done' => $loads->where('board_column', self::COLUMN_DONE)->count(),
            'overdue' => $loads->where('board_overdue', true)->count(),
            'completion' => $loads->isEmpty()
                ? 0
                : round((float) $loads->avg('board_progress'), 1),
        ];
    }

    private function dependencyCards(Collection $loads): Collection
    {
        return $loads
            ->groupBy('contracting_agency_id')
            ->map(function (Collection $agencyLoads) {
                /** @var ScheduledLoad $sample */
                $sample = $agencyLoads->first();
                $summary = $this->summarize($agencyLoads);
                $nextDue = $agencyLoads
                    ->filter(fn (ScheduledLoad $load) =>
                        $load->board_column !== self::COLUMN_DONE
                        && $load->effective_close_at !== null
                    )
                    ->sortBy('effective_close_at')
                    ->first()?->effective_close_at;

                return [
                    'agency' => $sample->agency,
                    'summary' => $summary,
                    'next_due' => $nextDue,
                    'initials' => $this->initials($sample->agency?->name ?? 'Dependencia'),
                    'logo_url' => data_get($sample->agency?->metadata, 'logo_url'),
                ];
            })
            ->sortBy(fn (array $card) => $card['agency']?->name)
            ->values();
    }

    private function scopeLabel(User $user, ?int $selectedUnitId, Collection $units): string
    {
        if ($selectedUnitId) {
            return $units->firstWhere('id', $selectedUnitId)?->name ?? 'Dirección seleccionada';
        }

        if (RoleCode::isDirectionDirector($user->role?->code)
            || RoleCode::isOperator($user->role?->code)) {
            return $user->organizationalUnit?->name ?? 'Dirección asignada';
        }

        return 'Todas las direcciones autorizadas';
    }

    private function initials(string $name): string
    {
        return str($name)
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(3)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    private function columnDefinitions(): array
    {
        return [
            self::COLUMN_TODO => [
                'label' => 'Por hacer',
                'description' => 'Programadas, abiertas o todavía no iniciadas.',
                'icon' => 'bi-list-check',
            ],
            self::COLUMN_PROGRESS => [
                'label' => 'En progreso',
                'description' => 'Captura activa, correcciones u observaciones.',
                'icon' => 'bi-hourglass-split',
            ],
            self::COLUMN_REVIEW => [
                'label' => 'En revisión',
                'description' => 'Pendientes de revisión y decisión del Enlace Institucional.',
                'icon' => 'bi-search',
            ],
            self::COLUMN_DONE => [
                'label' => 'Validadas y cerradas',
                'description' => 'Aceptadas y cerradas institucionalmente.',
                'icon' => 'bi-check2-circle',
            ],
        ];
    }
}
