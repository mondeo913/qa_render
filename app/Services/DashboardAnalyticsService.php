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

    private const REPROGRAMMED_STATUSES = [
        'SUSPENDIDA',
        'REPROGRAMADA',
        'REPROGRAMADA_ABIERTA',
        'REPROGRAMADA_ENTREGADA',
    ];

    public function __construct(private readonly AccessScopeService $access) {}

    public function forUser(User $user, array $filters = []): array
    {
        $base = fn(): Builder => $this->filteredBase($user, $filters);
        $total = (clone $base())->where('status', '!=', 'CANCELADA')->count();
        $closed = (clone $base())->where('status', 'VALIDADO_Y_CERRADO')->count();
        $validated = (clone $base())->where('status', 'VALIDADA')->count();
        $overdue = (clone $base())->where('status', 'VENCIDA')->count();
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

        $deliverableQuery = ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($deliverableQuery, $user);
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

        $deliverableFunnelQuery = ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($deliverableFunnelQuery, $user);
        $deliverableFunnel = $deliverableFunnelQuery->whereIn('scheduled_load_id', $accessibleLoadIds)->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->all();
        $evidenceFunnel = Evidence::query()->whereIn('scheduled_load_id', $accessibleLoadIds)->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->all();

        $agencyProgress = (clone $base())
            ->where('status', '!=', 'CANCELADA')
            ->join('contracting_agencies', 'contracting_agencies.id', '=', 'scheduled_loads.contracting_agency_id')
            ->select('contracting_agencies.name')
            ->selectRaw('COUNT(*) AS programmed')
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO','VALIDADA','VALIDADO_Y_CERRADO','REPROGRAMADA_ENTREGADA','REABIERTA') THEN 1 ELSE 0 END) AS realized")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN 1 ELSE 0 END) AS closed")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADA' THEN 1 ELSE 0 END) AS validated")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA','REPROGRAMADA_ENTREGADA') THEN 1 ELSE 0 END) AS reprogrammed")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','VENCIDA') THEN 1 ELSE 0 END) AS missing")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VENCIDA' THEN 1 ELSE 0 END) AS overdue")
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
            ->whereNotNull('scheduled_load_deliverables.organizational_unit_id');

        $directionProgress = $directionProgressQuery
            ->selectRaw('MIN(organizational_units.name) AS name')
            ->selectRaw('LOWER(TRIM(organizational_units.name)) AS normalized_name')
            ->selectRaw('COUNT(DISTINCT scheduled_loads.id) AS programmed')
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO','VALIDADA','VALIDADO_Y_CERRADO','REPROGRAMADA_ENTREGADA','REABIERTA') THEN scheduled_loads.id END) AS realized")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN scheduled_loads.id END) AS closed")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADA' THEN scheduled_loads.id END) AS validated")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA','REPROGRAMADA_ENTREGADA') THEN scheduled_loads.id END) AS reprogrammed")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status IN ('PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','VENCIDA') THEN scheduled_loads.id END) AS missing")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VENCIDA' THEN scheduled_loads.id END) AS overdue")
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

        $upcoming = (clone $base())->with(['agency', 'deliverables.organizationalUnit'])->where('effective_close_at', '>=', now())->orderBy('effective_close_at')->limit(8)->get();
        $recent = (clone $base())->with(['agency', 'deliverables.organizationalUnit'])->orderByDesc('updated_at')->limit(10)->get();
        $completionAverage = round((float)((clone $base())->avg('completion_percentage') ?? 0), 2);
        $reviewPending = (clone $base())->whereIn('status', ['ENTREGADA', 'EN_REVISION_INSTITUCIONAL'])->count();
        $observed = (clone $base())->where('status', 'OBSERVADA')->count();
        $dueSoon = (clone $base())->whereBetween('effective_close_at', [now(), now()->addDays(3)])->whereNotIn('status', ['VALIDADO_Y_CERRADO', 'CANCELADA'])->count();
        $agencyPerformance = $agencyProgress;
        $directionPerformance = $directionProgress;
        $riskItems = (clone $base())->with(['agency', 'deliverables.organizationalUnit'])->whereIn('status', ['VENCIDA', 'OBSERVADA', 'EN_REVISION_INSTITUCIONAL', 'PENDIENTE_DOCUMENTO_FIRMADO'])->orderByRaw("CASE status WHEN 'VENCIDA' THEN 1 WHEN 'OBSERVADA' THEN 2 ELSE 3 END")->orderBy('effective_close_at')->limit(15)->get();

        return [
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
            ],
            'status_distribution' => $statusDistribution,
            'monthly_trend' => $monthlyTrend,
            'unit_performance' => $unitPerformance,
            'deliverable_funnel' => $deliverableFunnel,
            'evidence_funnel' => $evidenceFunnel,
            'agency_performance' => $agencyPerformance,
            'direction_performance' => $directionPerformance,
            'pauta_summary' => $pautaSummary,
            'risk_items' => $riskItems,
            'upcoming' => $upcoming,
            'recent' => $recent,
        ];
    }

    private function filteredBase(User $user, array $filters): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);

        $roleCode = $user->role?->code;
        if (in_array($roleCode, ['DIRECTOR_GENERAL', 'ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)) {
            $query->whereIn('scheduled_loads.status', self::NON_OPERATOR_STATUSES);
        } elseif ($this->isOperator($user)) {
            // Los operadores mantienen el universo completo para el flujo operativo.
        }

        if (!empty($filters['agency_id'])) {
            $query->where('scheduled_loads.contracting_agency_id', (int)$filters['agency_id']);
        }

        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string)$filters['organizational_unit_id']))->map(fn($id)=>(int)trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $query->whereIn('scheduled_loads.id', function($q) use ($unitIds) {
                    $q->select('scheduled_load_id')
                        ->from('scheduled_load_deliverables')
                        ->whereIn('organizational_unit_id', $unitIds);
                });
            }
        }

        if (!empty($filters['status'])) {
            $query->where('scheduled_loads.status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $from = \Carbon\CarbonImmutable::createFromFormat('Y-m', $filters['from'])->startOfMonth();
            $query->where('scheduled_loads.effective_open_at', '>=', $from);
        }

        if (!empty($filters['to'])) {
            $to = \Carbon\CarbonImmutable::createFromFormat('Y-m', $filters['to'])->endOfMonth();
            $query->where('scheduled_loads.effective_open_at', '<=', $to);
        }

        return $query;
    }

    private function isOperator(User $user): bool
    {
        return in_array($user->role?->code, [
            'OPERADOR',
            'OPERADOR_TRANSMISION',
            'OPERADOR_PROGRAMACION_CONTINUIDAD',
        ], true);
    }
}
