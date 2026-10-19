<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Evidence;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;

final class DashboardAnalyticsService
{
    private const NON_OPERATOR_STATUSES = [
        'PROGRAMADA',
        'ABIERTA',
        'EN_CAPTURA',
        'PARCIALMENTE_ENTREGADA',
        'ENTREGADA',
        'EN_REVISION_INSTITUCIONAL',
        'OBSERVADA',
        'LISTA_PARA_FIRMA',
        'PENDIENTE_DOCUMENTO_FIRMADO',
        'VALIDADA',
        'VALIDADO_Y_CERRADO',
        'SUSPENDIDA',
        'REPROGRAMADA',
        'REPROGRAMADA_ABIERTA',
        'REPROGRAMADA_ENTREGADA',
        'VENCIDA',
        'REABIERTA',
    ];

    private const REALIZED_STATUSES = [
        'ENTREGADA',
        'EN_REVISION_INSTITUCIONAL',
        'OBSERVADA',
        'LISTA_PARA_FIRMA',
        'PENDIENTE_DOCUMENTO_FIRMADO',
        'VALIDADA',
        'VALIDADO_Y_CERRADO',
        'REPROGRAMADA_ENTREGADA',
        'REABIERTA',
    ];

    // Solo las cargas aún abiertas como reprogramación cuentan como "a reprogramar".
    // REPROGRAMADA_ENTREGADA ya fue realizada y por ello no debe duplicar el avance.
    private const REPROGRAMMED_STATUSES = [
        'SUSPENDIDA',
        'REPROGRAMADA',
        'REPROGRAMADA_ABIERTA',
    ];

    public function __construct(private readonly AccessScopeService $access) {}

    public function forUser(User $user, array $filters = []): array
    {
        $base = fn(): Builder => $this->loadsForUser($user, $filters);
        $total = (clone $base())->where('status', '!=', 'CANCELADA')->count();
        $closed = (clone $base())->where('status', 'VALIDADO_Y_CERRADO')->count();
        $validated = (clone $base())->where('status', 'VALIDADA')->count();
        $overdue = $this->overdueLoads(clone $base())->count();
        $reprogrammed = (clone $base())->whereIn('status', self::REPROGRAMMED_STATUSES)->count();
        $realized = (clone $base())->whereIn('status', self::REALIZED_STATUSES)->count();
        $pending = max(0, $total - $realized - $reprogrammed);
        $activeStatuses = ['PROGRAMADA', 'ABIERTA', 'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA', 'ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'OBSERVADA', 'LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO', 'VALIDADA', 'REABIERTA'];
        $active = (clone $base())->whereIn('status', $activeStatuses)->count();

        $statusDistributionQuery = (clone $base())
            ->where('status', '!=', 'CANCELADA')
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->orderByDesc('total');
        $statusDistribution = $statusDistributionQuery->pluck('total', 'status')->all();

        $monthlyTrend = (clone $base())
            ->where('status', '!=', 'CANCELADA')
            ->get(['effective_open_at', 'status'])
            ->groupBy(fn($load) => $load->effective_open_at->format('Y-m'))
            ->sortKeys()
            ->take(-12)
            ->map(function ($loads, $period) {
                $totalForMonth = $loads->count();
                $closedForMonth = $loads->filter(fn($load) => (($load->status instanceof \BackedEnum ? $load->status->value : (string)$load->status) === 'VALIDADO_Y_CERRADO'))->count();
                $realizedForMonth = $loads->filter(fn($load) => in_array(($load->status instanceof \BackedEnum ? $load->status->value : (string)$load->status), self::REALIZED_STATUSES, true))->count();
                return [
                    'period' => $period,
                    'total' => $totalForMonth,
                    'realized' => $realizedForMonth,
                    'closed' => $closedForMonth,
                    'compliance' => $totalForMonth === 0 ? 0 : round(100 * $closedForMonth / $totalForMonth, 2),
                ];
            })->values()->all();

        $accessibleLoadIds = (clone $base())->where('status', '!=', 'CANCELADA')->select('scheduled_loads.id');

        $deliverableQuery = $this->deliverablesForUser($user, $filters);
        $unitPerformance = $deliverableQuery
            ->join('organizational_units', 'organizational_units.id', '=', 'scheduled_load_deliverables.organizational_unit_id')
            ->whereIn('scheduled_load_deliverables.scheduled_load_id', $accessibleLoadIds)
            ->selectRaw('MIN(organizational_units.name) AS name')
            ->selectRaw('LOWER(TRIM(organizational_units.name)) AS normalized_name')
            ->selectRaw('COUNT(DISTINCT scheduled_load_deliverables.id) AS total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_load_deliverables.status IN ('VALIDADO','CERRADO') THEN scheduled_load_deliverables.id END) AS validated")
            ->groupByRaw('LOWER(TRIM(organizational_units.name))')
            ->orderBy('name')
            ->get()
            ->map(fn($row) => [
                'unit' => $row->name,
                'total' => (int)$row->total,
                'validated' => (int)$row->validated,
                'percentage' => (int)$row->total === 0 ? 0 : round(100 * (int)$row->validated / (int)$row->total, 2),
            ])->all();

        $deliverableFunnelQuery = $this->deliverablesForUser($user, $filters);
        $deliverableFunnel = $deliverableFunnelQuery->whereIn('scheduled_load_id', $accessibleLoadIds)->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->all();
        $visibleDeliverableIds = $this->deliverablesForUser($user, $filters)->whereIn('scheduled_load_id', $accessibleLoadIds)->select('scheduled_load_deliverables.id');
        $evidenceFunnel = Evidence::query()->whereIn('deliverable_id', $visibleDeliverableIds)->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->all();

        $evidenceDeliverablesQuery = $this->deliverablesForUser($user, $filters);
        $evidenceDeliverables = $evidenceDeliverablesQuery
            ->with(['evidences:id,deliverable_id,scheduled_load_id,submitted_by,status,submitted_at,validated_at,current_version', 'organizationalUnit:id,name,code,unit_type', 'responsibleUser:id,name', 'templateRequirement:id,name', 'scheduledLoad:id,title,calendar_import_id,contracting_agency_id,effective_close_at,status,is_blocked', 'scheduledLoad.calendarImport:id,original_filename', 'scheduledLoad.agency:id,name'])
            ->whereIn('scheduled_load_id', $accessibleLoadIds)
            ->whereNotIn('status', ['CANCELADA'])
            ->get();
        $expectedEvidence = $evidenceDeliverables->count();
        $receivedEvidence = $evidenceDeliverables->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
        $validatedEvidence = $evidenceDeliverables->filter(fn ($d) => in_array($d->status->value, ['VALIDADO', 'CERRADO'], true))->count();
        $observedEvidence = $evidenceDeliverables->filter(fn ($d) => in_array($d->status->value, ['OBSERVADO', 'RECHAZADO'], true))->count();
        $reviewEvidence = $evidenceDeliverables->filter(fn ($d) => in_array($d->status->value, ['EN_REVISION', 'ENVIADO'], true))->count();
        $pendingEvidence = max(0, $expectedEvidence - $receivedEvidence);
        $evidenceSummary = [
            'expected' => $expectedEvidence,
            'received' => $receivedEvidence,
            'validated' => $validatedEvidence,
            'pending' => $pendingEvidence,
            'observed' => $observedEvidence,
            'review' => $reviewEvidence,
            'delivery_percentage' => $expectedEvidence ? round(100 * $receivedEvidence / $expectedEvidence, 1) : 0,
            'validation_percentage' => $receivedEvidence ? round(100 * $validatedEvidence / $receivedEvidence, 1) : 0,
            'observation_percentage' => $receivedEvidence ? round(100 * $observedEvidence / $receivedEvidence, 1) : 0,
        ];
        $evidenceByUnit = $evidenceDeliverables->groupBy(fn ($d) => $d->organizationalUnit?->name ?: 'Sin dirección')->map(function ($rows, $unit) {
            $expected = $rows->count();
            $received = $rows->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
            $validated = $rows->filter(fn ($d) => in_array($d->status->value, ['VALIDADO', 'CERRADO'], true))->count();
            return ['unit' => $unit, 'expected' => $expected, 'received' => $received, 'validated' => $validated, 'pending' => max(0, $expected - $received), 'percentage' => $expected ? round(100 * $received / $expected, 1) : 0];
        })->sortBy('percentage')->values()->all();
        $evidenceByDirection = $evidenceDeliverables
            ->filter(fn ($d) => in_array($d->organizationalUnit?->code, ['DIR_A', 'DIR_B'], true))
            ->groupBy(fn ($d) => $d->organizationalUnit?->name ?: 'Sin dirección')
            ->map(function ($rows, $unit) {
                $expected = $rows->count();
                $received = $rows->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
                $validated = $rows->filter(fn ($d) => in_array($d->status->value, ['VALIDADO', 'CERRADO'], true))->count();
                return ['unit' => $unit, 'expected' => $expected, 'received' => $received, 'validated' => $validated, 'pending' => max(0, $expected - $received), 'percentage' => $expected ? round(100 * $received / $expected, 1) : 0];
            })->sortBy('unit')->values()->all();
        $evidenceTrend = $evidenceDeliverables->groupBy(fn ($d) => DashboardSnapshotService::deadline($d)?->format('Y-m') ?: 'Sin fecha')->sortKeys()->take(-6)->map(function ($rows, $period) {
            $expected = $rows->count();
            $received = $rows->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
            $validated = $rows->filter(fn ($d) => in_array($d->status->value, ['VALIDADO', 'CERRADO'], true))->count();
            return ['period' => $period, 'expected' => $expected, 'received' => $received, 'validated' => $validated];
        })->values()->all();
        $pendingByDue = $evidenceDeliverables->filter(fn ($d) => DashboardSnapshotService::group($d) === 'pending')->groupBy(function ($d) {
            if ($d->scheduledLoad?->is_blocked || in_array($d->scheduledLoad?->status?->value, ['SUSPENDIDA', 'REPROGRAMADA'], true)) return 'Bloqueadas / suspendidas';
            if (!$deadline = DashboardSnapshotService::deadline($d)) return 'Sin fecha';
            $days = now()->diffInDays($deadline, false);
            return $days < 0 ? 'Vencidas' : ($days <= 3 ? 'Hasta 3 días' : ($days <= 7 ? '4 a 7 días' : 'Más de 7 días'));
        })->map(fn ($rows, $bucket) => ['bucket' => $bucket, 'total' => $rows->count()])->values()->all();
        $evidenceByCampaign = $evidenceDeliverables
            ->groupBy(fn ($d) => $d->scheduledLoad?->calendarImport?->id ?: 'legacy-'.$d->scheduled_load_id)
            ->map(function ($rows) {
                $import = $rows->first()?->scheduledLoad?->calendarImport;
                $pautaName = $import?->original_filename ?: ($rows->first()?->scheduledLoad?->title ?: 'Pauta sin nombre');
                $expected = $rows->count();
                $received = $rows->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
                return [
                    'campaign' => $pautaName,
                    'pauta' => $pautaName,
                    'pauta_id' => $import?->id ? (int) $import->id : null,
                    'expected' => $expected,
                    'received' => $received,
                    'percentage' => $expected ? round(100 * $received / $expected, 1) : 0,
                ];
            })->sortBy('percentage')->take(5)->values()->all();

        $evidenceByResponsible = $evidenceDeliverables
            ->filter(fn ($d) => $d->responsibleUser)
            ->groupBy('responsible_user_id')
            ->map(function ($rows, $responsible) {
                $expected = $rows->count();
                $received = $rows->filter(fn ($d) => ($d->submitted_at !== null || $d->evidences->contains(fn ($e) => $e->submitted_at !== null)))->count();
                return [
                    'responsible_id' => (int) $responsible,
                    'responsible' => $rows->first()->responsibleUser->name,
                    'expected' => $expected,
                    'received' => $received,
                    'pending' => max(0, $expected - $received),
                    'percentage' => $expected ? round(100 * $received / $expected, 1) : 0,
                ];
            })->sortByDesc('expected')->values()->all();

        $agencyProgress = (clone $base())
            ->where('status', '!=', 'CANCELADA')
            ->join('contracting_agencies', 'contracting_agencies.id', '=', 'scheduled_loads.contracting_agency_id')
            ->select('contracting_agencies.name')
            ->selectRaw('COUNT(*) AS programmed')
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO','VALIDADA','VALIDADO_Y_CERRADO','REPROGRAMADA_ENTREGADA','REABIERTA') THEN 1 ELSE 0 END) AS realized")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN 1 ELSE 0 END) AS closed")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADA' THEN 1 ELSE 0 END) AS validated")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA') THEN 1 ELSE 0 END) AS reprogrammed")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','VENCIDA') THEN 1 ELSE 0 END) AS missing")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VENCIDA' OR (scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','REABIERTA','REPROGRAMADA_ABIERTA') AND scheduled_loads.is_blocked = false AND scheduled_loads.effective_close_at < ?) THEN 1 ELSE 0 END) AS overdue", [now()])
            ->groupBy('contracting_agencies.id', 'contracting_agencies.name')
            ->orderByDesc('programmed')
            ->limit(30)
            ->get()
            ->map(function ($r) {
                $programmed = (int)$r->programmed;
                $realized = (int)$r->realized;
                $reprogrammed = (int)$r->reprogrammed;
                return [
                    'agency' => $r->name,
                    'programmed' => $programmed,
                    'realized' => $realized,
                    'closed' => (int)$r->closed,
                    'validated' => (int)$r->validated,
                    'reprogrammed' => $reprogrammed,
                    'missing' => max(0, $programmed - $realized - $reprogrammed),
                    'overdue' => (int)$r->overdue,
                    'percentage' => $programmed === 0 ? 0 : round(100 * $realized / $programmed, 1),
                    'closure_percentage' => $programmed === 0 ? 0 : round(100 * ((int)$r->closed) / $programmed, 1),
                ];
            })->all();

        $directionProgressQuery = (clone $base())
            ->where('scheduled_loads.status', '!=', 'CANCELADA')
            ->join('scheduled_load_deliverables', 'scheduled_load_deliverables.scheduled_load_id', '=', 'scheduled_loads.id')
            ->join('organizational_units', 'organizational_units.id', '=', 'scheduled_load_deliverables.organizational_unit_id')
            ->whereNotNull('scheduled_load_deliverables.organizational_unit_id')
            ->whereIn('scheduled_load_deliverables.id', $visibleDeliverableIds);

        $directionProgress = $directionProgressQuery
            ->selectRaw('MIN(organizational_units.name) AS name')
            ->selectRaw('LOWER(TRIM(organizational_units.name)) AS normalized_name')
            ->selectRaw('COUNT(DISTINCT scheduled_loads.id) AS programmed')
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO','VALIDADA','VALIDADO_Y_CERRADO','REPROGRAMADA_ENTREGADA','REABIERTA') THEN scheduled_loads.id END) AS realized")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN scheduled_loads.id END) AS closed")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADA' THEN scheduled_loads.id END) AS validated")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA') THEN scheduled_loads.id END) AS reprogrammed")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','VENCIDA') THEN scheduled_loads.id END) AS missing")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VENCIDA' OR (scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','REABIERTA','REPROGRAMADA_ABIERTA') AND scheduled_loads.is_blocked = false AND scheduled_loads.effective_close_at < ?) THEN scheduled_loads.id END) AS overdue", [now()])
            ->groupByRaw('LOWER(TRIM(organizational_units.name))')
            ->orderByDesc('programmed')
            ->limit(40)
            ->get()
            ->map(function ($r) {
                $programmed = (int)$r->programmed;
                $realized = (int)$r->realized;
                $reprogrammed = (int)$r->reprogrammed;
                return [
                    'unit' => $r->name,
                    'programmed' => $programmed,
                    'realized' => $realized,
                    'closed' => (int)$r->closed,
                    'validated' => (int)$r->validated,
                    'reprogrammed' => $reprogrammed,
                    'missing' => max(0, $programmed - $realized - $reprogrammed),
                    'overdue' => (int)$r->overdue,
                    'percentage' => $programmed === 0 ? 0 : round(100 * $realized / $programmed, 1),
                ];
            })->all();

        $pautaSummary = [
            'programmed' => $total,
            'realized' => $realized,
            'validated' => $validated,
            'closed' => $closed,
            'reprogrammed' => $reprogrammed,
            'missing' => $pending,
            'overdue' => $overdue,
        ];

        $upcomingQuery = (clone $base())->whereNotIn('status', ['CANCELADA', 'VALIDADO_Y_CERRADO'])->where('effective_close_at', '>=', now())->orderBy('effective_close_at')->limit(8);
        $upcomingQuery->with([
            'agency',
            'deliverables' => function ($query) use ($user) {
                $this->access->scopeDeliverables($query, $user);
                $query->with(['organizationalUnit', 'responsibleUser']);
            },
        ]);
        $upcoming = $upcomingQuery->get();
        $recent = (clone $base())->with(['agency', 'deliverables' => function ($query) use ($user, $filters) {
            $query->whereIn('scheduled_load_deliverables.id', $this->deliverablesForUser($user, $filters)->select('scheduled_load_deliverables.id'));
            $query->with(['organizationalUnit', 'responsibleUser']);
        }])->orderByDesc('updated_at')->limit(10)->get();
        $completionAverage = round((float)((clone $base())->avg('completion_percentage') ?? 0), 2);
        $reviewPending = (clone $base())->whereIn('status', ['ENTREGADA', 'EN_REVISION_INSTITUCIONAL'])->count();
        $observed = (clone $base())->where('status', 'OBSERVADA')->count();
        $dueSoon = (clone $base())->whereBetween('effective_close_at', [now(), now()->addDays(3)])->whereNotIn('status', ['VALIDADO_Y_CERRADO', 'CANCELADA'])->count();
        $agencyPerformance = $agencyProgress;
        $directionPerformance = $directionProgress;
        $riskItems = (clone $base())->with([
            'agency',
            'deliverables' => function ($query) use ($user) {
                $this->access->scopeDeliverables($query, $user);
                $query->with(['organizationalUnit', 'responsibleUser']);
            },
        ])->where(function ($q) {
            $q->whereIn('status', ['VENCIDA', 'OBSERVADA', 'EN_REVISION_INSTITUCIONAL', 'PENDIENTE_DOCUMENTO_FIRMADO'])
                ->orWhere(fn ($expired) => $this->overdueLoads($expired));
        })->orderByRaw("CASE status WHEN 'VENCIDA' THEN 1 WHEN 'OBSERVADA' THEN 2 ELSE 3 END")->orderBy('effective_close_at')->limit(15)->get();

        return [
            'workspace' => app(DashboardSnapshotService::class)->forUser($evidenceDeliverables, $user),
            'institutional_queue' => (clone $base())->with('agency')->whereIn('status', ['ENTREGADA', 'EN_REVISION_INSTITUCIONAL', 'LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO', 'VALIDADA'])->orderBy('effective_close_at')->limit(10)->get(),
            'signature_pending' => (clone $base())->whereIn('status', ['LISTA_PARA_FIRMA', 'PENDIENTE_DOCUMENTO_FIRMADO'])->count(),
            'kpis' => [
                'total' => $total,
                'active' => $active,
                'closed' => $closed,
                'validated' => $validated,
                'realized' => $realized,
                'pending' => $pending,
                'overdue' => $overdue,
                'reprogrammed' => $reprogrammed,
                'compliance' => $total === 0 ? 0 : round(100 * $closed / $total, 2),
                'completion_average' => $completionAverage,
                'review_pending' => $reviewPending,
                'observed' => $observed,
                'due_soon' => $dueSoon,
                'evidence_expected' => $expectedEvidence,
                'evidence_received' => $receivedEvidence,
            ],
            'status_distribution' => $statusDistribution,
            'monthly_trend' => $monthlyTrend,
            'unit_performance' => $unitPerformance,
            'deliverable_funnel' => $deliverableFunnel,
            'evidence_funnel' => $evidenceFunnel,
            'evidence_summary' => $evidenceSummary,
            'evidence_by_unit' => $evidenceByUnit,
            'evidence_by_direction' => $evidenceByDirection,
            'evidence_trend' => $evidenceTrend,
            'pending_by_due' => $pendingByDue,
            'evidence_by_campaign' => $evidenceByCampaign,
            'evidence_by_responsible' => $evidenceByResponsible,
            'agency_performance' => $agencyPerformance,
            'direction_performance' => $directionPerformance,
            'pauta_summary' => $pautaSummary,
            'risk_items' => $riskItems,
            'upcoming' => $upcoming,
            'recent' => $recent,
        ];
    }

    public function loadsForUser(User $user, array $filters): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);

        $roleCode = $user->role?->code;
        if (($filters['status'] ?? '') !== 'CANCELADA' && in_array($roleCode, ['DIRECTOR_GENERAL', 'ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)) {
            $query->whereIn('scheduled_loads.status', self::NON_OPERATOR_STATUSES);
        }

        if (!empty($filters['agency_id'])) {
            $query->where('scheduled_loads.contracting_agency_id', (int)$filters['agency_id']);
        }

        if (!empty($filters['organizational_unit_id']) || !empty($filters['responsible_id']) || !empty($filters['deliverable_group'])) {
            $query->whereIn('scheduled_loads.id', $this->deliverablesForUser($user, $filters)->select('scheduled_load_deliverables.scheduled_load_id'));
        }

        if (!empty($filters['pauta_id'])) {
            // La Pauta es la importación del Excel. Todas las cargas generadas
            // por ese archivo comparten calendar_import_id.
            $query->where('scheduled_loads.calendar_import_id', (int) $filters['pauta_id']);
        } elseif (!empty($filters['campaign'])) {
            // Compatibilidad con enlaces antiguos: antes "campaign" filtraba
            // por scheduled_loads.title, que representa una carga diaria, no la pauta.
            $campaignFilter = explode('::', (string) $filters['campaign'], 2);
            $campaignAgencyId = isset($campaignFilter[1]) && ctype_digit($campaignFilter[0])
                ? (int) $campaignFilter[0]
                : null;
            $campaignTitle = $campaignAgencyId !== null
                ? (string) $campaignFilter[1]
                : (string) $filters['campaign'];

            if ($campaignAgencyId !== null && $campaignAgencyId > 0) {
                $query->where('scheduled_loads.contracting_agency_id', $campaignAgencyId);
            }

            $query->where('scheduled_loads.title', $campaignTitle);
        }

        if (!empty($filters['status'])) {
            $query->where('scheduled_loads.status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $from = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $filters['from'])->startOfMonth();
            $query->where('scheduled_loads.effective_open_at', '>=', $from);
        }

        if (!empty($filters['to'])) {
            $to = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $filters['to'])->endOfMonth();
            $query->where('scheduled_loads.effective_open_at', '<=', $to);
        }

        return $query;
    }

    public function deliverablesForUser(User $user, array $filters): Builder
    {
        $query = $this->access->scopeDeliverables(ScheduledLoadDeliverable::query(), $user);
        if (!empty($filters['organizational_unit_id'])) {
            $ids = array_filter(array_map('intval', explode(',', $filters['organizational_unit_id'])));
            $query->whereIn('scheduled_load_deliverables.organizational_unit_id', $ids);
        }
        if (!empty($filters['responsible_id'])) {
            $query->where('scheduled_load_deliverables.responsible_user_id', (int) $filters['responsible_id']);
        }
        if (isset(DashboardSnapshotService::GROUPS[$filters['deliverable_group'] ?? ''])) {
            $query->whereIn('scheduled_load_deliverables.status', DashboardSnapshotService::GROUPS[$filters['deliverable_group']]);
        }
        return $query;
    }

    private function overdueLoads(Builder $query): Builder
    {
        // Same deadline warning as the calendar: delivered, suspended and blocked loads are excluded.
        return $query->where(function ($q) {
            $q->where('scheduled_loads.status', 'VENCIDA')->orWhere(function ($pending) {
                $pending->whereIn('scheduled_loads.status', ['PROGRAMADA', 'ABIERTA', 'EN_CAPTURA', 'PARCIALMENTE_ENTREGADA', 'REABIERTA', 'REPROGRAMADA_ABIERTA'])
                    ->where('scheduled_loads.is_blocked', false)->where('scheduled_loads.effective_close_at', '<', now());
            });
        });
    }
}
