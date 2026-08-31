<form method="GET" class="card siget-card mb-4" id="siget-dashboard-filters">
<div class="card-header"><div><h2>Contexto de análisis</h2><p>Los filtros acotan el universo visible sin modificar la lógica ni los permisos de SIGET.</p></div></div>
@php
    $filterAgencies = $filterAgencies ?? [];
    $filterUnits = $filterUnits ?? [];
    $periodMin = $periodMin ?? null;
    $periodMax = $periodMax ?? null;
    $selectedFrom = $filters['from'] ?? '';
    $selectedTo = $filters['to'] ?? '';
@endphp
<div class="card-body row g-3 align-items-end">
<div class="col-xl-3 col-md-6">
<label class="form-label">Dependencia</label>
<select name="agency_id" id="siget-agency-filter" class="form-select">
<option value="">Todas las dependencias</option>
@foreach($filterAgencies as $agency)
@php $agencyId=data_get($agency,'id'); $agencyName=data_get($agency,'name',''); @endphp
<option value="{{ $agencyId }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agencyId)>{{ $agencyName }}</option>
@endforeach
</select>
</div>
<div class="col-xl-3 col-md-6">
<label class="form-label">Dirección / unidad</label>
<select name="organizational_unit_id" class="form-select">
<option value="">Todas las direcciones / unidades</option>
@foreach($filterUnits as $unit)
@php
$unitId=data_get($unit,'id');
$unitName=data_get($unit,'name','');
$filterIds=data_get($unit,'filter_unit_ids');
$filterIds=is_array($filterIds) ? $filterIds : [$unitId];
$filterIds=implode(',',array_map('strval',$filterIds));
@endphp
<option value="{{ $filterIds }}" @selected((string)($filters['organizational_unit_id'] ?? '') === $filterIds)>{{ $unitName }}</option>
@endforeach
</select>
</div>
<div class="col-xl-2 col-md-4">
<label class="form-label">Estado</label>
<select name="status" class="form-select">
<option value="">Todos</option>
@foreach(['PROGRAMADA'=>'PROGRAMADO','REPROGRAMADA'=>'REPROGRAMADO','VALIDADO_Y_CERRADO'=>'VALIDADO Y CERRADO','VENCIDA'=>'VENCIDO'] as $status => $label)
<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $label }}</option>
@endforeach
</select>
</div>
<div class="col-xl-2 col-md-4">
<label class="form-label">Mes inicial</label>
<input type="month" name="from" id="siget-period-from" value="{{ $selectedFrom }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control">
</div>
<div class="col-xl-2 col-md-4">
<label class="form-label">Mes final</label>
<input type="month" name="to" id="siget-period-to" value="{{ $selectedTo }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control">
</div>
<div class="col-12">
<div class="siget-period-segmenter border rounded-3 p-3">
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
<div><strong>Segmentador de periodo contratado</strong><div class="small text-muted">Selecciona un rango de meses. Los límites disponibles se calculan a partir de las pautas/cargas accesibles.</div></div>
<span class="badge text-bg-light" id="siget-period-summary">{{ $selectedFrom && $selectedTo ? $selectedFrom.' → '.$selectedTo : ($periodMin && $periodMax ? $periodMin.' → '.$periodMax : 'Sin periodo disponible') }}</span>
</div>
</div>
</div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button><a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Restablecer</a></div>
</div>
</form>

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
    const from=document.getElementById('siget-period-from');
    const to=document.getElementById('siget-period-to');
    const summary=document.getElementById('siget-period-summary');
    const updateRange=()=>{
        if(!from||!to) return;
        if(from.value) to.min=from.value;
        if(to.value) from.max=to.value;
        if(from.value && to.value) summary.textContent=from.value+' → '+to.value;
        else if(from.value) summary.textContent=from.value+' → Selecciona mes final';
        else if(to.value) summary.textContent='Selecciona mes inicial → '+to.value;
    };
    from?.addEventListener('change',updateRange);
    to?.addEventListener('change',updateRange);
    document.getElementById('siget-dashboard-filters')?.addEventListener('submit',function(event){
        if(from?.value && to?.value && from.value>to.value){event.preventDefault();to.setCustomValidity('El mes final debe ser igual o posterior al mes inicial.');to.reportValidity();to.setCustomValidity('');}
    });
    updateRange();
    const applyTrendFontScale=()=>{
        document.querySelectorAll('.siget-trend-modern canvas').forEach(canvas=>{
            if(typeof Chart==='undefined') return;
            const chart=Chart.getChart(canvas);
            if(!chart || chart.$sigetFontScaled10) return;
            chart.$sigetFontScaled10=true;
            const legend=chart.options?.plugins?.legend?.labels;
            if(legend){legend.font={...(typeof legend.font==='object'?legend.font:{}),size:12};}
            const scales=chart.options?.scales||{};
            ['x','y','y1'].forEach(axis=>{const s=scales[axis];if(!s)return;s.ticks={...(s.ticks||{}),font:{...(typeof s.ticks?.font==='object'?s.ticks.font:{}),size:11}};if(s.title)s.title.font={...(typeof s.title.font==='object'?s.title.font:{}),size:12};});
            if(chart.options?.plugins?.tooltip){chart.options.plugins.tooltip.titleFont={size:12};chart.options.plugins.tooltip.bodyFont={size:11};}
            chart.update('none');
        });
    };
    const observer=new MutationObserver(applyTrendFontScale);
    observer.observe(document.body,{childList:true,subtree:true});
    document.addEventListener('DOMContentLoaded',()=>setTimeout(applyTrendFontScale,150));
    setTimeout(applyTrendFontScale,500);
})();
</script>
