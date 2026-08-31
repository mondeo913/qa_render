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

    public function __construct(private readonly AccessScopeService $access) {}

    /** @param array{agency_id?: int|null, unit_id?: string|int|null, from?: string|null, to?: string|null, q?: string|null, mine?: bool|null} $filters */
    public function forUser(User $user, array $filters = []): array
    {
        $normalized = [
            'agency_id' => isset($filters['agency_id']) && $filters['agency_id'] !== '' ? (int) $filters['agency_id'] : null,
            'unit_id' => trim((string) ($filters['unit_id'] ?? '')) ?: null,
            'from' => trim((string) ($filters['from'] ?? '')) ?: null,
            'to' => trim((string) ($filters['to'] ?? '')) ?: null,
            'q' => trim((string) ($filters['q'] ?? '')) ?: null,
            'mine' => (bool) ($filters['mine'] ?? false),
        ];

        $availableAgencies = $this->availableAgencies($user);
        $availableUnits = $this->availableUnits($user, $normalized['agency_id']);

        if ($normalized['agency_id'] !== null && !$availableAgencies->contains('id', $normalized['agency_id'])) {
            $normalized['agency_id'] = null;
        }

        $selectedUnitIds = $this->parseIds($normalized['unit_id']);
        $allowedUnitIds = $availableUnits->flatMap(fn ($unit) => (array) ($unit->filter_unit_ids ?? [$unit->id]))->map(fn ($id) => (int) $id)->unique()->values()->all();
        $selectedUnitIds = array_values(array_intersect($selectedUnitIds, $allowedUnitIds));
        $normalized['unit_id'] = $selectedUnitIds ? implode(',', $selectedUnitIds) : null;

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

        $columns = collect($this->columnDefinitions())->mapWithKeys(fn (array $definition, string $key) => [
            $key => $definition + ['loads' => $boardLoads->where('board_column', $key)->values()],
        ]);

        return [
            'filters' => $normalized,
            'columns' => $columns,
            'summary' => $this->summarize($boardLoads),
            'dependencyCards' => $this->dependencyCards($catalogLoads),
            'agencies' => $availableAgencies,
            'units' => $availableUnits,
            'periods' => $this->availablePeriods($user, $normalized),
            'periodBounds' => $this->periodBounds($user, $normalized),
            'scopeLabel' => $this->scopeLabel($user, $normalized['unit_id'], $availableUnits),
            'canUseMineFilter' => RoleCode::isOperator($user->role?->code),
        ];
    }

    public static function columnForStatus(string $status, float $completion = 0): string
    {
        return match (strtoupper($status)) {
            'VALIDADA', 'VALIDADO_Y_CERRADO' => self::COLUMN_DONE,
            'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'REPROGRAMADA_ENTREGADA', 'LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO' => self::COLUMN_REVIEW,
            'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA', 'OBSERVADA', 'REABIERTA' => self::COLUMN_PROGRESS,
            'VENCIDA' => $completion > 0 ? self::COLUMN_PROGRESS : self::COLUMN_TODO,
            default => self::COLUMN_TODO,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'PROGRAMADA' => 'Programada', 'ABIERTA' => 'Ventana abierta', 'EN_CAPTURA' => 'En captura',
            'PARCIALMENTE_ENTREGADA' => 'Entrega parcial', 'ENTREGADA' => 'Entregada', 'EN_REVISION_INSTITUCIONAL' => 'Revisión institucional',
            'OBSERVADA' => 'Con observaciones', 'LISTA_PARA_FIRMA' => 'Lista para firma', 'PENDIENTE_DOCUMENTO_FIRMADO' => 'Pendiente de documento firmado',
            'VALIDADA' => 'Validada', 'VALIDADO_Y_CERRADO' => 'Validada y cerrada', 'SUSPENDIDA' => 'Suspendida',
            'REPROGRAMADA' => 'Reprogramada', 'REPROGRAMADA_ABIERTA' => 'Reprogramada abierta', 'REPROGRAMADA_ENTREGADA' => 'Reprogramada entregada',
            'VENCIDA' => 'Vencida', 'CANCELADA' => 'Cancelada', 'REABIERTA' => 'Reabierta',
            default => str($status)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    private function baseQuery(User $user, array $filters, bool $includeAgencyFilter): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        $unitIds = $this->parseIds($filters['unit_id'] ?? null);

        $query->with(['agency:id,code,name,metadata', 'deliverables' => function ($deliverables) use ($user, $filters, $unitIds) {
            $this->access->scopeDeliverables($deliverables, $user);
            if ($unitIds) $deliverables->whereIn('organizational_unit_id', $unitIds);
            if ($filters['mine'] && RoleCode::isOperator($user->role?->code)) $deliverables->where('responsible_user_id', $user->id);
            $deliverables->with(['organizationalUnit:id,code,name', 'responsibleUser:id,name', 'evidences:id,deliverable_id,status']);
        }]);

        if ($includeAgencyFilter && $filters['agency_id']) $query->where('contracting_agency_id', $filters['agency_id']);
        if ($unitIds) $query->whereHas('deliverables', fn (Builder $d) => $d->whereIn('organizational_unit_id', $unitIds));
        if ($filters['mine'] && RoleCode::isOperator($user->role?->code)) $query->whereHas('deliverables', fn (Builder $d) => $d->where('responsible_user_id', $user->id));

        $this->applyMonthRange($query, $filters['from'], $filters['to']);

        if ($filters['q']) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $query->where(fn (Builder $search) => $search->where('title', 'like', $term)->orWhere('period_label', 'like', $term)->orWhereHas('agency', fn (Builder $agency) => $agency->where('name', 'like', $term)->orWhere('code', 'like', $term)));
        }
        return $query;
    }

    private function availableAgencies(User $user): Collection
    {
        return ContractingAgency::query()
            ->select(['id', 'code', 'name', 'metadata'])
            ->where('active', true)
            ->whereHas('scheduledLoads', fn (Builder $loads) => $this->access->scopeLoads($loads, $user))
            ->orderBy('name')->get();
    }

    private function availableUnits(User $user, ?int $agencyId = null): Collection
    {
        $unitIds = $this->access->accessibleUnitIds($user);
        $query = OrganizationalUnit::query()->select(['id', 'contracting_agency_id', 'code', 'name'])->where('active', true);
        if ($unitIds !== []) $query->whereIn('id', $unitIds);
        else $query->whereHas('deliverables.scheduledLoad', fn (Builder $loads) => $this->access->scopeLoads($loads, $user));
        if ($agencyId) $query->where('contracting_agency_id', $agencyId);

        $units = $query->orderBy('name')->get();
        return $units->groupBy(fn ($unit) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $unit->name))))
            ->map(function (Collection $group) {
                $unit = $group->first();
                $unit->name = preg_replace('/\s+/u', ' ', trim((string) $unit->name));
                $unit->filter_unit_ids = $group->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
                return $unit;
            })->sortBy(fn ($unit) => mb_strtolower($unit->name))->values();
    }

    private function availablePeriods(User $user, array $filters = []): Collection
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        if (!empty($filters['agency_id'])) $query->where('contracting_agency_id', (int) $filters['agency_id']);
        $unitIds = $this->parseIds($filters['unit_id'] ?? null);
        if ($unitIds) $query->whereHas('deliverables', fn (Builder $d) => $d->whereIn('organizational_unit_id', $unitIds));
        return $query->whereNotNull('effective_open_at')->whereNotNull('effective_close_at')->select(['effective_open_at','effective_close_at'])->get()
            ->flatMap(function ($load) {
                $start = $load->effective_open_at->copy()->startOfMonth();
                $end = $load->effective_close_at->copy()->startOfMonth();
                $months = collect();
                for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) $months->push($cursor->format('Y-m'));
                return $months;
            })->unique()->sortDesc()->values();
    }

    private function periodBounds(User $user, array $filters = []): array
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        if (!empty($filters['agency_id'])) $query->where('contracting_agency_id', (int) $filters['agency_id']);
        $unitIds = $this->parseIds($filters['unit_id'] ?? null);
        if ($unitIds) $query->whereHas('deliverables', fn (Builder $d) => $d->whereIn('organizational_unit_id', $unitIds));
        $dates = $query->whereNotNull('effective_open_at')->whereNotNull('effective_close_at')->select(['effective_open_at','effective_close_at'])->get();
        if ($dates->isEmpty()) return ['min' => null, 'max' => null];
        return ['min' => $dates->min('effective_open_at')->copy()->startOfMonth()->format('Y-m'), 'max' => $dates->max('effective_close_at')->copy()->startOfMonth()->format('Y-m')];
    }

    private function applyMonthRange(Builder $query, ?string $from, ?string $to): void
    {
        if ($from && preg_match('/^\d{4}-\d{2}$/', $from)) $query->where('effective_close_at', '>=', $from . '-01 00:00:00');
        if ($to && preg_match('/^\d{4}-\d{2}$/', $to)) {
            $end = \Carbon\Carbon::createFromFormat('Y-m', $to)->endOfMonth();
            $query->where('effective_open_at', '<=', $end);
        }
    }

    private function parseIds(?string $value): array
    {
        return collect(explode(',', (string) $value))->map(fn ($id) => (int) trim($id))->filter(fn ($id) => $id > 0)->unique()->values()->all();
    }

    private function decorateLoads(Collection $loads): void
    {
        foreach ($loads as $load) {
            $progress = $this->visibleProgress($load);
            $status = $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status;
            $load->setAttribute('board_progress', $progress);
            $load->setAttribute('board_column', self::columnForStatus($status, $progress));
            $load->setAttribute('board_status_label', self::statusLabel($status));
            $load->setAttribute('board_overdue', $this->isOverdue($load, $status));
            $load->setAttribute('board_unit_names', $load->deliverables->pluck('organizationalUnit.name')->filter()->unique()->values());
            $load->setAttribute('board_responsibles', $load->deliverables->pluck('responsibleUser.name')->filter()->unique()->values());
            $load->setAttribute('board_evidence_count', $load->deliverables->sum(fn ($d) => $d->evidences->count()));
            $load->setAttribute('board_observation_count', $load->deliverables->filter(fn ($d) => trim((string) $d->observations) !== '')->count());
        }
    }

    private function visibleProgress(ScheduledLoad $load): float
    {
        if ($load->deliverables->isEmpty()) return (float) ($load->completion_percentage ?? 0);
        $weights = [DeliverableStatus::PENDIENTE->value=>0, DeliverableStatus::EN_CAPTURA->value=>20, DeliverableStatus::ENVIADO->value=>45, DeliverableStatus::EN_REVISION->value=>60, DeliverableStatus::OBSERVADO->value=>45, DeliverableStatus::CORREGIDO->value=>65, DeliverableStatus::VALIDADO->value=>90, DeliverableStatus::CERRADO->value=>100];
        return round($load->deliverables->avg(function ($d) use ($weights) { $status = $d->status instanceof \BackedEnum ? $d->status->value : (string) $d->status; return $weights[$status] ?? 0; }), 2);
    }

    private function isOverdue(ScheduledLoad $load, string $status): bool
    {
        if (in_array($status, ['VALIDADA','VALIDADO_Y_CERRADO','CANCELADA'], true)) return false;
        return $status === 'VENCIDA' || ($load->effective_close_at && $load->effective_close_at->isPast());
    }

    private function summarize(Collection $loads): array
    {
        return ['total'=>$loads->count(),'todo'=>$loads->where('board_column',self::COLUMN_TODO)->count(),'progress'=>$loads->where('board_column',self::COLUMN_PROGRESS)->count(),'review'=>$loads->where('board_column',self::COLUMN_REVIEW)->count(),'done'=>$loads->where('board_column',self::COLUMN_DONE)->count(),'overdue'=>$loads->where('board_overdue',true)->count(),'completion'=>$loads->isEmpty()?0:round((float)$loads->avg('board_progress'),1)];
    }

    private function dependencyCards(Collection $loads): Collection
    {
        return $loads->groupBy('contracting_agency_id')->map(function (Collection $agencyLoads) { $sample=$agencyLoads->first(); $summary=$this->summarize($agencyLoads); $nextDue=$agencyLoads->filter(fn ($load)=>$load->board_column!==self::COLUMN_DONE && $load->effective_close_at!==null)->sortBy('effective_close_at')->first()?->effective_close_at; return ['agency'=>$sample->agency,'summary'=>$summary,'next_due'=>$nextDue,'initials'=>$this->initials($sample->agency?->name ?? 'Dependencia'),'logo_url'=>data_get($sample->agency?->metadata,'logo_url')]; })->sortBy(fn(array $card)=>$card['agency']?->name)->values();
    }

    private function scopeLabel(User $user, ?string $selectedUnitId, Collection $units): string
    {
        $ids=$this->parseIds($selectedUnitId);
        if ($ids) return $units->firstWhere(fn($unit)=>in_array((int)$unit->id,$ids,true))?->name ?? 'Dirección seleccionada';
        if (RoleCode::isDirectionDirector($user->role?->code) || RoleCode::isOperator($user->role?->code)) return $user->organizationalUnit?->name ?? 'Dirección asignada';
        return 'Todas las direcciones autorizadas';
    }

    private function initials(string $name): string
    {
        return str($name)->replaceMatches('/[^\pL\pN\s]+/u',' ')->squish()->explode(' ')->filter()->take(3)->map(fn(string $part)=>mb_strtoupper(mb_substr($part,0,1)))->implode('');
    }

    private function columnDefinitions(): array
    {
        return [self::COLUMN_TODO=>['label'=>'Por hacer','description'=>'Programadas, abiertas o todavía no iniciadas.','icon'=>'bi-list-check'],self::COLUMN_PROGRESS=>['label'=>'En progreso','description'=>'Captura activa, correcciones u observaciones.','icon'=>'bi-hourglass-split'],self::COLUMN_REVIEW=>['label'=>'En revisión','description'=>'Pendientes de revisión y decisión del Enlace Institucional.','icon'=>'bi-search'],self::COLUMN_DONE=>['label'=>'Validadas y cerradas','description'=>'Aceptadas y cerradas institucionalmente.','icon'=>'bi-check2-circle']];
    }
}
