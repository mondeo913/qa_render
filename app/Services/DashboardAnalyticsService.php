<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardAnalyticsService
{
    public function __construct(private readonly AccessScopeService $access) {}

    public function forUser(User $user, array $filters = []): array
    {
        $base = fn (): Builder => $this->filteredBase($user, $filters);
        $total = (clone $base())->count();
        $closed = (clone $base())->where('status', 'VALIDADO_Y_CERRADO')->count();
        $overdue = (clone $base())->where('status', 'VENCIDA')->count();
        $reprogrammed = (clone $base())->whereIn('status', ['SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA','REPROGRAMADA_ENTREGADA'])->count();
        $activeStatuses = ['PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','LISTA_PARA_FIRMA','PENDIENTE_DOCUMENTO_FIRMADO','VALIDADA','REABIERTA'];
        $active = (clone $base())->whereIn('status', $activeStatuses)->count();
        $statusDistribution = (clone $base())->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->orderByDesc('total')->pluck('total', 'status')->all();
        $monthlyTrend = (clone $base())->get(['effective_open_at','status'])->groupBy(fn ($load) => $load->effective_open_at->format('Y-m'))->sortKeys()->take(-12)->map(function ($loads, $period) {
            $totalForMonth = $loads->count();
            $closedForMonth = $loads->filter(fn ($load) => (($load->status instanceof \BackedEnum ? $load->status->value : (string)$load->status) === 'VALIDADO_Y_CERRADO'))->count();
            return ['period'=>$period,'total'=>$totalForMonth,'closed'=>$closedForMonth,'compliance'=>$totalForMonth===0?0:round(100*$closedForMonth/$totalForMonth,2)];
        })->values()->all();

        $accessibleLoadIds = (clone $base())->select('scheduled_loads.id');
        $deliverableQuery = ScheduledLoadDeliverable::query(); $this->access->scopeDeliverables($deliverableQuery, $user);
        $unitPerformance = $deliverableQuery->join('organizational_units','organizational_units.id','=','scheduled_load_deliverables.organizational_unit_id')->whereIn('scheduled_load_deliverables.scheduled_load_id',$accessibleLoadIds)->selectRaw('MIN(organizational_units.name) AS name')->selectRaw('LOWER(TRIM(organizational_units.name)) AS normalized_name')->selectRaw('COUNT(*) AS total')->selectRaw("SUM(CASE WHEN scheduled_load_deliverables.status IN ('VALIDADO','CERRADO') THEN 1 ELSE 0 END) AS validated")->groupByRaw('LOWER(TRIM(organizational_units.name))')->orderBy('name')->get()->map(fn ($row)=>['unit'=>$row->name,'total'=>(int)$row->total,'validated'=>(int)$row->validated,'percentage'=>(int)$row->total===0?0:round(100*(int)$row->validated/(int)$row->total,2)])->all();
        $deliverableFunnelQuery = ScheduledLoadDeliverable::query(); $this->access->scopeDeliverables($deliverableFunnelQuery, $user);
        $deliverableFunnel = $deliverableFunnelQuery->whereIn('scheduled_load_id',$accessibleLoadIds)->select('status',DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total','status')->all();
        $evidenceFunnel = Evidence::query()->whereIn('scheduled_load_id',$accessibleLoadIds)->select('status',DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total','status')->all();
        $upcoming = (clone $base())->with(['agency','deliverables.organizationalUnit'])->where('effective_close_at','>=',now())->orderBy('effective_close_at')->limit(8)->get();
        $recent = (clone $base())->with(['agency','deliverables.organizationalUnit'])->orderByDesc('updated_at')->limit(10)->get();
        $completionAverage = round((float)((clone $base())->avg('completion_percentage') ?? 0),2);
        $reviewPending = (clone $base())->whereIn('status',['ENTREGADA','EN_REVISION_INSTITUCIONAL'])->count();
        $observed = (clone $base())->where('status','OBSERVADA')->count();
        $dueSoon = (clone $base())->whereBetween('effective_close_at',[now(),now()->addDays(3)])->whereNotIn('status',['VALIDADO_Y_CERRADO','CANCELADA'])->count();
        $agencyPerformance = (clone $base())->join('contracting_agencies','contracting_agencies.id','=','scheduled_loads.contracting_agency_id')->select('contracting_agencies.name')->selectRaw('COUNT(*) AS total')->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN 1 ELSE 0 END) AS closed")->selectRaw("SUM(CASE WHEN scheduled_loads.status='VENCIDA' THEN 1 ELSE 0 END) AS overdue")->groupBy('contracting_agencies.id','contracting_agencies.name')->orderByDesc('overdue')->limit(20)->get()->map(fn($r)=>['agency'=>$r->name,'total'=>(int)$r->total,'closed'=>(int)$r->closed,'overdue'=>(int)$r->overdue,'percentage'=>(int)$r->total===0?0:round(100*(int)$r->closed/(int)$r->total,1)])->all();
        $directionPerformance = (clone $base())->join('scheduled_load_deliverables','scheduled_load_deliverables.scheduled_load_id','=','scheduled_loads.id')->join('organizational_units','organizational_units.id','=','scheduled_load_deliverables.organizational_unit_id')->selectRaw('MIN(organizational_units.name) AS name')->selectRaw('LOWER(TRIM(organizational_units.name)) AS normalized_name')->selectRaw('COUNT(DISTINCT scheduled_loads.id) AS total')->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN scheduled_loads.id END) AS closed")->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VENCIDA' THEN scheduled_loads.id END) AS overdue")->groupByRaw('LOWER(TRIM(organizational_units.name))')->orderByDesc('overdue')->limit(30)->get()->map(fn($r)=>['unit'=>$r->name,'total'=>(int)$r->total,'closed'=>(int)$r->closed,'overdue'=>(int)$r->overdue,'percentage'=>(int)$r->total===0?0:round(100*(int)$r->closed/(int)$r->total,1)])->all();
        $riskItems = (clone $base())->with(['agency','deliverables.organizationalUnit'])->whereIn('status',['VENCIDA','OBSERVADA','EN_REVISION_INSTITUCIONAL','PENDIENTE_DOCUMENTO_FIRMADO'])->orderByRaw("CASE status WHEN 'VENCIDA' THEN 1 WHEN 'OBSERVADA' THEN 2 ELSE 3 END")->orderBy('effective_close_at')->limit(15)->get();

        return ['kpis'=>['total'=>$total,'active'=>$active,'closed'=>$closed,'overdue'=>$overdue,'reprogrammed'=>$reprogrammed,'compliance'=>$total===0?0:round(100*$closed/$total,2),'completion_average'=>$completionAverage,'review_pending'=>$reviewPending,'observed'=>$observed,'due_soon'=>$dueSoon], 'status_distribution'=>$statusDistribution,'monthly_trend'=>$monthlyTrend,'unit_performance'=>$unitPerformance,'deliverable_funnel'=>$deliverableFunnel,'evidence_funnel'=>$evidenceFunnel,'agency_performance'=>$agencyPerformance,'direction_performance'=>$directionPerformance,'risk_items'=>$riskItems,'upcoming'=>$upcoming,'recent'=>$recent];
    }

    private function filteredBase(User $user, array $filters): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        if (!empty($filters['agency_id'])) $query->where('contracting_agency_id',(int)$filters['agency_id']);
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) $query->whereIn('scheduled_loads.id', function ($q) use ($unitIds) { $q->select('scheduled_load_id')->from('scheduled_load_deliverables')->whereIn('organizational_unit_id',$unitIds); });
        }
        if (!empty($filters['status'])) $query->where('status',$filters['status']);
        if (!empty($filters['from'])) $query->whereDate('effective_open_at','>=',$filters['from']);
        if (!empty($filters['to'])) $query->whereDate('effective_open_at','<=',$filters['to']);
        return $query;
    }
}
