<form method="GET" class="card siget-card mb-4" id="siget-dashboard-filters">
<div class="card-header"><div><h2>Contexto de análisis</h2><p>Los filtros acotan el universo visible sin modificar la lógica ni los permisos de SIGET.</p></div></div>
@php
    $filterAgencies = $filterAgencies ?? [];
    $filterUnits = $filterUnits ?? [];
    $periodMin = $periodMin ?? null;
    $periodMax = $periodMax ?? null;
    $selectedFrom = $filters['from'] ?? '';
    $selectedTo = $filters['to'] ?? '';
    $roleCode = auth()->user()?->role?->code;
    $operatorRoles = ['OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD'];
    $isOperatorDashboard = in_array($roleCode, $operatorRoles, true);
    $executiveRoles = ['ADMINISTRADOR','DIRECTOR_GENERAL','ENLACE_INSTITUCIONAL'];
    $executiveStatuses = [
        'PROGRAMADA' => 'PROGRAMADA',
        'ENTREGADA' => 'REALIZADA / ENTREGADA',
        'VALIDADA' => 'VALIDADA',
        'VALIDADO_Y_CERRADO' => 'VALIDADA Y CERRADA',
        'REPROGRAMADA' => 'REPROGRAMADA',
        'REPROGRAMADA_ENTREGADA' => 'REPROGRAMADA ENTREGADA',
        'VENCIDA' => 'VENCIDA / FALTANTE',
    ];
    $operationalStatuses = [
        'PROGRAMADA' => 'PROGRAMADA',
        'ABIERTA' => 'VENTANA ABIERTA',
        'EN_CAPTURA' => 'EN CAPTURA',
        'PARCIALMENTE_ENTREGADA' => 'ENTREGA PARCIAL',
        'ENTREGADA' => 'REALIZADA / ENTREGADA',
        'EN_REVISION_INSTITUCIONAL' => 'EN REVISIÓN INSTITUCIONAL',
        'OBSERVADA' => 'OBSERVADA',
        'LISTA_PARA_FIRMA' => 'LISTA PARA FIRMA',
        'PENDIENTE_DOCUMENTO_FIRMADO' => 'PENDIENTE DOCUMENTO FIRMADO',
        'VALIDADA' => 'VALIDADA',
        'VALIDADO_Y_CERRADO' => 'VALIDADA Y CERRADA',
        'SUSPENDIDA' => 'SUSPENDIDA / REPROGRAMACIÓN',
        'REPROGRAMADA' => 'REPROGRAMADA',
        'REPROGRAMADA_ABIERTA' => 'REPROGRAMADA ABIERTA',
        'REPROGRAMADA_ENTREGADA' => 'REPROGRAMADA ENTREGADA',
        'VENCIDA' => 'VENCIDA',
        'REABIERTA' => 'REABIERTA',
        'CANCELADA' => 'CANCELADA',
    ];
    $availableStatuses = in_array($roleCode, $executiveRoles, true) || !$isOperatorDashboard
        ? $executiveStatuses
        : $operationalStatuses;
@endphp
<div class="card-body row g-3 align-items-end">
<div class="col-xl-3 col-md-6"><label class="form-label">Dependencia</label><select name="agency_id" id="siget-agency-filter" class="form-select"><option value="">Todas las dependencias</option>@foreach($filterAgencies as $agency)@php $agencyId=data_get($agency,'id'); $agencyName=data_get($agency,'name',''); @endphp<option value="{{ $agencyId }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agencyId)>{{ $agencyName }}</option>@endforeach</select></div>
<div class="col-xl-3 col-md-6"><label class="form-label">Dirección / unidad</label><select name="organizational_unit_id" class="form-select"><option value="">Todas las direcciones / unidades</option>@foreach($filterUnits as $unit)@php $unitId=data_get($unit,'id'); $unitName=data_get($unit,'name',''); $filterIds=data_get($unit,'filter_unit_ids'); $filterIds=is_array($filterIds) ? $filterIds : [$unitId]; $filterIds=implode(',',array_map('strval',$filterIds)); @endphp<option value="{{ $filterIds }}" @selected((string)($filters['organizational_unit_id'] ?? '') === $filterIds)>{{ $unitName }}</option>@endforeach</select></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Estado de carga</label><select name="status" class="form-select"><option value="">Todos los estados relevantes</option>@foreach($availableStatuses as $status => $label)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $label }}</option>@endforeach</select></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Fecha contratada desde</label><input type="month" name="from" id="siget-period-from" value="{{ $selectedFrom }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control"></div>
<div class="col-xl-2 col-md-4"><label class="form-label">Fecha contratada hasta</label><input type="month" name="to" id="siget-period-to" value="{{ $selectedTo }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control"></div>
<div class="col-12"><div class="siget-period-segmenter border rounded-3 p-3"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong>Rango de fechas contratado según pauta</strong><div class="small text-muted">El rango se aplica a la fecha efectiva de apertura de cada carga y se acota al universo accesible del usuario.</div></div><span class="badge text-bg-light" id="siget-period-summary">{{ $selectedFrom && $selectedTo ? $selectedFrom.' → '.$selectedTo : ($periodMin && $periodMax ? $periodMin.' → '.$periodMax : 'Sin periodo disponible') }}</span></div></div></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button><a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Restablecer</a></div>
</div>
</form>
<script>
(function(){
 const from=document.getElementById('siget-period-from'),to=document.getElementById('siget-period-to'),summary=document.getElementById('siget-period-summary');
 const update=()=>{if(!from||!to)return;if(from.value)to.min=from.value;if(to.value)from.max=to.value;if(from.value&&to.value)summary.textContent=from.value+' → '+to.value;else if(from.value)summary.textContent=from.value+' → Selecciona mes final';else if(to.value)summary.textContent='Selecciona mes inicial → '+to.value;};
 from?.addEventListener('change',update);to?.addEventListener('change',update);
 document.getElementById('siget-dashboard-filters')?.addEventListener('submit',e=>{if(from?.value&&to?.value&&from.value>to.value){e.preventDefault();to.setCustomValidity('La fecha contratada final debe ser igual o posterior a la fecha inicial.');to.reportValidity();to.setCustomValidity('');}});update();
})();
</script>
