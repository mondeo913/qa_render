<?php

namespace App\Services;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IntelligenceAnalyticsService
{
    public function __construct(
        private readonly AccessScopeService $access,
        private readonly DashboardAnalyticsService $dashboard,
    ) {}

    public function forUser(User $user, array $filters = []): array
    {
        $analytics = $this->dashboard->forUser($user, $filters);
        $base = fn (): Builder => $this->filteredBase($user, $filters);
        $accessibleLoadIds = (clone $base())->select('scheduled_loads.id');
        $now = now();

        $deliverables = ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($deliverables, $user);
        $deliverables->whereIn('scheduled_load_deliverables.scheduled_load_id', $accessibleLoadIds)
            ->whereHas('templateRequirement', fn (Builder $q) => $q->where('required', true));

        $missingEvidence = (clone $deliverables)->whereDoesntHave('evidences')->count();
        $reviewQueue = (clone $deliverables)->whereIn('scheduled_load_deliverables.status', ['ENVIADO','EN_REVISION','OBSERVADO','RECHAZADO'])->count();
        $staleReviews = (clone $deliverables)
            ->whereIn('scheduled_load_deliverables.status', ['ENVIADO','EN_REVISION'])
            ->where('scheduled_load_deliverables.updated_at', '<', $now->copy()->subDays(3))
            ->count();

        $agencyPerformance = $this->agencyPerformance($base);
        $directionPerformance = $this->directionPerformance($base);
        $attention = $this->attentionItems($user, $filters, $accessibleLoadIds);
        $quality = $this->qualityItems($user, $accessibleLoadIds);

        $radar = ['CRÍTICO'=>0,'ATENCIÓN'=>0,'NORMAL'=>0];
        foreach ($agencyPerformance as $row) {
            $risk = ($row['overdue'] ?? 0) > 0 || ($row['percentage'] ?? 0) < 50
                ? 'CRÍTICO'
                : (($row['due_soon'] ?? 0) > 0 || ($row['percentage'] ?? 0) < 80 ? 'ATENCIÓN' : 'NORMAL');
            $radar[$risk]++;
        }

        $analytics['generated_at'] = $now;
        $analytics['agency_performance'] = $agencyPerformance;
        $analytics['direction_performance'] = $directionPerformance;
        $analytics['radar_summary'] = $radar;
        $analytics['attention_items'] = $attention;
        $analytics['quality_items'] = $quality;
        $analytics['kpis']['missing_evidence'] = $missingEvidence;
        $analytics['kpis']['review_queue'] = $reviewQueue;
        $analytics['kpis']['stale_reviews'] = $staleReviews;
        $analytics['kpis']['attention_total'] = count($attention);

        return $analytics;
    }

    public function filterOptions(User $user): array
    {
        $loadQuery = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        $loadIds = (clone $loadQuery)->select('scheduled_loads.id');

        $agencyIds = (clone $loadQuery)
            ->whereNotNull('scheduled_loads.contracting_agency_id')
            ->distinct()
            ->pluck('scheduled_loads.contracting_agency_id');

        $agencies = ContractingAgency::query()
            ->where('active', true)
            ->whereIn('id', $agencyIds)
            ->orderBy('name')
            ->get();

        $unitQuery = ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($unitQuery, $user);
        $unitIds = (clone $unitQuery)
            ->whereIn('scheduled_load_deliverables.scheduled_load_id', $loadIds)
            ->whereNotNull('scheduled_load_deliverables.organizational_unit_id')
            ->distinct()
            ->pluck('scheduled_load_deliverables.organizational_unit_id');

        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->whereIn('id', $unitIds)
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($unit) => mb_strtolower(preg_replace('/\\s+/u', ' ', trim((string) $unit->name))))
            ->map(function (Collection $group) {
                $unit = $group->first();
                $unit->name = preg_replace('/\\s+/u', ' ', trim((string) $unit->name));
                $unit->filter_unit_ids = $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                return $unit;
            })
            ->sortBy(fn ($unit) => mb_strtolower((string) $unit->name))
            ->values();

        return ['agencies'=>$agencies,'units'=>$units];
    }

    private function filteredBase(User $user, array $filters): Builder
    {
        $query = $this->access->scopeLoads(ScheduledLoad::query(), $user);

        if (!empty($filters['agency_id'])) {
            $query->where('scheduled_loads.contracting_agency_id', (int) $filters['agency_id']);
        }

        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($unitIds !== []) {
                $query->whereIn('scheduled_loads.id', function ($q) use ($unitIds) {
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
            $query->whereDate('scheduled_loads.effective_open_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('scheduled_loads.effective_open_at', '<=', $filters['to']);
        }

        return $query;
    }

    private function agencyPerformance(callable $base): array
    {
        $now = now();
        $until = $now->copy()->addDays(3);

        return (clone $base())
            ->join('contracting_agencies','contracting_agencies.id','=','scheduled_loads.contracting_agency_id')
            ->select('contracting_agencies.id','contracting_agencies.name')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN 1 ELSE 0 END) AS closed")
            ->selectRaw("SUM(CASE WHEN scheduled_loads.status='VENCIDA' THEN 1 ELSE 0 END) AS overdue")
            ->selectRaw(
                "SUM(CASE WHEN scheduled_loads.effective_close_at BETWEEN ? AND ? AND scheduled_loads.status NOT IN ('VALIDADO_Y_CERRADO','CANCELADA','VENCIDA') THEN 1 ELSE 0 END) AS due_soon",
                [$now, $until]
            )
            ->groupBy('contracting_agencies.id','contracting_agencies.name')
            ->orderByDesc('overdue')
            ->orderByDesc('due_soon')
            ->get()
            ->map(fn ($row) => $this->riskRow((int) $row->id,$row->name,(int) $row->total,(int) $row->closed,(int) $row->overdue,(int) $row->due_soon))
            ->all();
    }

    private function directionPerformance(callable $base): array
    {
        $now = now();
        $until = $now->copy()->addDays(3);

        return (clone $base())
            ->join('scheduled_load_deliverables','scheduled_load_deliverables.scheduled_load_id','=','scheduled_loads.id')
            ->join('organizational_units','organizational_units.id','=','scheduled_load_deliverables.organizational_unit_id')
            ->select('organizational_units.id','organizational_units.name')
            ->selectRaw('COUNT(DISTINCT scheduled_loads.id) AS total')
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VALIDADO_Y_CERRADO' THEN scheduled_loads.id END) AS closed")
            ->selectRaw("COUNT(DISTINCT CASE WHEN scheduled_loads.status='VENCIDA' THEN scheduled_loads.id END) AS overdue")
            ->selectRaw(
                "COUNT(DISTINCT CASE WHEN scheduled_loads.effective_close_at BETWEEN ? AND ? AND scheduled_loads.status NOT IN ('VALIDADO_Y_CERRADO','CANCELADA','VENCIDA') THEN scheduled_loads.id END) AS due_soon",
                [$now, $until]
            )
            ->whereNotNull('scheduled_load_deliverables.organizational_unit_id')
            ->groupBy('organizational_units.id','organizational_units.name')
            ->orderByDesc('overdue')
            ->orderByDesc('due_soon')
            ->get()
            ->map(fn ($row) => $this->riskRow((int) $row->id,$row->name,(int) $row->total,(int) $row->closed,(int) $row->overdue,(int) $row->due_soon))
            ->all();
    }

    private function riskRow(int $id, string $name, int $total, int $closed, int $overdue, int $dueSoon): array
    {
        return [
            'id'=>$id,
            'name'=>preg_replace('/\\s+/u',' ',trim($name)),
            'total'=>$total,
            'closed'=>$closed,
            'overdue'=>$overdue,
            'due_soon'=>$dueSoon,
            'percentage'=>$total === 0 ? 0 : round(100*$closed/$total,1),
        ];
    }

    private function attentionItems(User $user, array $filters, $accessibleLoadIds): array
    {
        $base = $this->filteredBase($user,$filters)->with('agency');
        $items=[];

        foreach ((clone $base)->whereIn('scheduled_loads.status',['VENCIDA','OBSERVADA','PENDIENTE_DOCUMENTO_FIRMADO'])
            ->orderBy('scheduled_loads.effective_close_at')->limit(10)->get() as $load) {
            $status=$load->status instanceof \BackedEnum ? $load->status->value : (string)$load->status;
            $items[]=[
                'priority'=>in_array($status,['VENCIDA','OBSERVADA'],true)?1:2,
                'level'=>in_array($status,['VENCIDA','OBSERVADA'],true)?'CRÍTICO':'ATENCIÓN',
                'load_id'=>$load->id,
                'title'=>$status==='VENCIDA'?'Carga vencida':($status==='OBSERVADA'?'Carga observada':'Pendiente de cierre'),
                'description'=>($load->agency?->name??'Sin dependencia').' · '.($load->title?:'Carga #'.$load->id),
                'detail'=>$status==='VENCIDA'?'El periodo programado ya venció.':'La carga requiere atención antes del cierre.',
                'date'=>$load->effective_close_at,
            ];
        }

        foreach ((clone $base)->whereBetween('scheduled_loads.effective_close_at',[now(),now()->addDays(3)])
            ->whereNotIn('scheduled_loads.status',['VALIDADO_Y_CERRADO','CANCELADA','VENCIDA'])
            ->orderBy('scheduled_loads.effective_close_at')->limit(8)->get() as $load) {
            $items[]=[
                'priority'=>3,'level'=>'ATENCIÓN','load_id'=>$load->id,'title'=>'Vencimiento próximo',
                'description'=>($load->agency?->name??'Sin dependencia').' · '.($load->title?:'Carga #'.$load->id),
                'detail'=>'La carga vence en las próximas 72 horas.','date'=>$load->effective_close_at,
            ];
        }

        $pending = ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($pending,$user);
        $pending->whereIn('scheduled_load_deliverables.scheduled_load_id',$accessibleLoadIds)
            ->whereHas('templateRequirement',fn (Builder $q)=>$q->where('required',true))
            ->whereIn('scheduled_load_deliverables.status',['ENVIADO','EN_REVISION','OBSERVADO','RECHAZADO'])
            ->with(['scheduledLoad.agency','organizationalUnit','templateRequirement'])
            ->orderBy('scheduled_load_deliverables.updated_at');

        foreach ($pending->limit(10)->get() as $deliverable) {
            $status=$deliverable->status instanceof \BackedEnum ? $deliverable->status->value : (string)$deliverable->status;
            $critical=in_array($status,['OBSERVADO','RECHAZADO'],true);
            $items[]=[
                'priority'=>$critical?2:4,'level'=>$critical?'CRÍTICO':'ATENCIÓN','load_id'=>$deliverable->scheduled_load_id,
                'title'=>$critical?'Entregable observado':'Pendiente de revisión',
                'description'=>($deliverable->organizationalUnit?->name??'Dirección no identificada').' · '.($deliverable->templateRequirement?->name??'Entregable'),
                'detail'=>$critical?'Debe atenderse la observación antes del cierre.':'Requiere revisión dentro del flujo institucional.',
                'date'=>$deliverable->due_at,
            ];
        }

        return collect($items)
            ->sortBy(fn ($item)=>sprintf('%02d-%s',(int)($item['priority']??9),optional($item['date']??null)->format('Y-m-d H:i:s')??'9999-12-31 23:59:59'))
            ->take(15)->values()->all();
    }

    private function qualityItems(User $user, $accessibleLoadIds): array
    {
        $query=ScheduledLoadDeliverable::query();
        $this->access->scopeDeliverables($query,$user);
        $query->whereIn('scheduled_load_deliverables.scheduled_load_id',$accessibleLoadIds)
            ->whereHas('templateRequirement',fn (Builder $q)=>$q->where('required',true))
            ->with(['scheduledLoad.agency','organizationalUnit','templateRequirement','evidences.files'])
            ->orderBy('scheduled_load_deliverables.updated_at');

        $items=[];
        foreach ($query->limit(80)->get() as $deliverable) {
            $evidence=$deliverable->evidences->sortByDesc('id')->first();
            $deliverableStatus=$deliverable->status instanceof \BackedEnum ? $deliverable->status->value : (string)$deliverable->status;
            $unitName=$deliverable->organizationalUnit?->name??'Dirección no identificada';
            $requirementName=$deliverable->templateRequirement?->name??'Entregable';

            if(!$evidence) {
                $items[]=['level'=>'CRÍTICO','kind'=>'deliverable','load_id'=>$deliverable->scheduled_load_id,'title'=>'Evidencia faltante','description'=>$unitName.' · '.$requirementName,'detail'=>'El entregable requerido aún no tiene evidencia registrada.'];
                continue;
            }

            $evidenceStatus=$evidence->status instanceof \BackedEnum ? $evidence->status->value : (string)$evidence->status;
            if($deliverable->due_at && $deliverable->submitted_at && $deliverable->submitted_at->gt($deliverable->due_at)) {
                $items[]=['level'=>'ATENCIÓN','kind'=>'evidence','evidence_id'=>$evidence->id,'load_id'=>$deliverable->scheduled_load_id,'title'=>'Entrega fuera de fecha','description'=>$unitName.' · '.$requirementName,'detail'=>'La evidencia fue entregada después de la fecha programada.'];
            }
            if(in_array($evidenceStatus,['OBSERVADO','RECHAZADO'],true) || in_array($deliverableStatus,['OBSERVADO','RECHAZADO'],true)) {
                $items[]=['level'=>'CRÍTICO','kind'=>'evidence','evidence_id'=>$evidence->id,'load_id'=>$deliverable->scheduled_load_id,'title'=>'Evidencia con observación','description'=>$unitName.' · '.$requirementName,'detail'=>'Existe una observación que debe resolverse antes del cierre.'];
            }
            if($evidence->files->isEmpty()) {
                $items[]=['level'=>'CRÍTICO','kind'=>'evidence','evidence_id'=>$evidence->id,'load_id'=>$deliverable->scheduled_load_id,'title'=>'Evidencia sin archivos','description'=>$unitName.' · '.$requirementName,'detail'=>'La evidencia existe, pero no tiene archivos asociados.'];
            }
        }

        return collect($items)->unique(fn($item)=>implode('|',[$item['kind']??'',$item['load_id']??'',$item['evidence_id']??'',$item['title']??'']))->take(12)->values()->all();
    }
}
