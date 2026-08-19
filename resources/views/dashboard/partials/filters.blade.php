<form method="GET" class="card siget-card mb-4">
<div class="card-header"><div><h2>Contexto de análisis</h2><p>Los filtros solo cambian el alcance de visualización; no modifican la lógica de SIGET.</p></div></div>
@php
    // El dashboard usa $agencies/$units para series analíticas; los filtros usan
    // explícitamente los catálogos originales enviados por DashboardController.
    $filterAgencies = $filterAgencies ?? [];
    $filterUnits = $filterUnits ?? [];
@endphp
<div class="card-body row g-3 align-items-end">
<div class="col-xl-3 col-md-6"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select"><option value="">Todas las dependencias</option>@foreach($filterAgencies as $agency)@php $agencyId=data_get($agency,'id'); $agencyName=data_get($agency,'name',''); @endphp<option value="{{ $agencyId }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agencyId)>{{ $agencyName }}</option>@endforeach</select></div>
<div class="col-xl-3 col-md-6"><label class="form-label">Dirección / unidad</label><select name="organizational_unit_id" class="form-select"><option value="">Todas las direcciones / unidades</option>@foreach($filterUnits as $unit)@php $unitId=data_get($unit,'id'); $unitName=data_get($unit,'name',''); $filterIds=data_get($unit,'filter_unit_ids'); $filterIds=is_array($filterIds) ? $filterIds : [$unitId]; $filterIds=implode(',',array_map('strval',$filterIds)); @endphp<option value="{{ $filterIds }}" @selected((string)($filters['organizational_unit_id'] ?? '') === $filterIds)>{{ $unitName }}</option>@endforeach</select></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Estado</label><select name="status" class="form-select"><option value="">Todos</option>@php
$isExecutiveDashboard = (($role ?? null) === 'DIRECTOR_GENERAL');
$dashboardStatuses = $isExecutiveDashboard
    ? ['REPROGRAMADA','VENCIDA','VALIDADO_Y_CERRADO']
    : ['PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','VALIDADA','VALIDADO_Y_CERRADO','VENCIDA','REPROGRAMADA'];
@endphp
@foreach($dashboardStatuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Desde</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button><a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Restablecer</a></div>
</div></form>

<style id="siget-trend-font-scale-10">
.siget-trend-modern .stm-title{font-size:1.155rem}
.siget-trend-modern .stm-sub{font-size:.792rem}
.siget-trend-modern .stm-question{font-size:.814rem}
.siget-trend-modern .stm-kpi-label{font-size:.693rem}
.siget-trend-modern .stm-kpi-value{font-size:1.485rem}
.siget-trend-modern .stm-kpi-note{font-size:.66rem}
.siget-trend-modern .stm-read{font-size:.748rem}
.siget-trend-modern .stm-foot-title{font-size:.726rem}
.siget-trend-modern .stm-foot-text{font-size:.649rem}
</style>
<script>
(function(){
    const applyTrendFontScale=()=>{
        document.querySelectorAll('.siget-trend-modern canvas').forEach(canvas=>{
            if(typeof Chart==='undefined') return;
            const chart=Chart.getChart(canvas);
            if(!chart || chart.$sigetFontScaled10) return;
            chart.$sigetFontScaled10=true;
            const legend=chart.options?.plugins?.legend?.labels;
            if(legend){legend.font={...(typeof legend.font==='object'?legend.font:{}),size:12};}
            const scales=chart.options?.scales||{};
            ['x','y','y1'].forEach(axis=>{
                const s=scales[axis];
                if(!s) return;
                s.ticks={...(s.ticks||{}),font:{...(typeof s.ticks?.font==='object'?s.ticks.font:{}),size:11}};
                if(s.title){s.title.font={...(typeof s.title.font==='object'?s.title.font:{}),size:12};}
            });
            if(chart.options?.plugins?.tooltip){
                chart.options.plugins.tooltip.titleFont={size:12};
                chart.options.plugins.tooltip.bodyFont={size:11};
            }
            chart.update('none');
        });
    };
    const observer=new MutationObserver(applyTrendFontScale);
    observer.observe(document.body,{childList:true,subtree:true});
    document.addEventListener('DOMContentLoaded',()=>setTimeout(applyTrendFontScale,150));
    setTimeout(applyTrendFontScale,500);
})();
</script>