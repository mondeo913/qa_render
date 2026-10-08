<form method="GET" class="siget-op-filter-form" id="siget-dashboard-filters" action="{{ route('dashboard') }}">
@php
    $filterAgencies = $filterAgencies ?? [];
    $filterUnits = $filterUnits ?? [];
    $filterPautas = $filterPautas ?? [];
    $periodMin = $periodMin ?? null;
    $periodMax = $periodMax ?? null;
    $selectedFrom = $filters['from'] ?? '';
    $selectedTo = $filters['to'] ?? '';
    $roleCode = auth()->user()?->role?->code;
    $operatorRoles = ['OPERADOR','OPERADOR_TRANSMISION','OPERADOR_PROGRAMACION_CONTINUIDAD'];
    $isOperatorDashboard = in_array($roleCode, $operatorRoles, true);
    $executiveRoles = ['ADMINISTRADOR','DIRECTOR_GENERAL','ENLACE_INSTITUCIONAL'];
    $executiveStatuses = [
        'PROGRAMADA' => 'PROGRAMADO',
        'REPROGRAMADA' => 'REPROGRAMADO',
        'VALIDADO_Y_CERRADO' => 'VALIDADO Y CERRADO',
        'VENCIDA' => 'VENCIDO',
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



<div class="op-filter-row">
    <div class="op-filter-control">
        <i class="bi bi-bank"></i>
        <label class="visually-hidden" for="siget-agency-filter">Dependencia</label>
        <select name="agency_id" id="siget-agency-filter" aria-label="Dependencia">
            <option value="">Todas las dependencias</option>
            @foreach($filterAgencies as $agency)
                @php $agencyId=data_get($agency,'id'); $agencyName=data_get($agency,'name',''); @endphp
                <option value="{{ $agencyId }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agencyId)>{{ $agencyName }}</option>
            @endforeach
        </select>
    </div>

    <div class="op-filter-control">
        <i class="bi bi-megaphone"></i>
        <label class="visually-hidden" for="siget-pauta-filter">Campaña</label>
        <select name="pauta_id" id="siget-pauta-filter" aria-label="Campaña">
            <option value="">Todas las campañas</option>
            @foreach(($filterPautas ?? []) as $pauta)
                <option value="{{ data_get($pauta,'value','') }}"
                        data-agency-id="{{ data_get($pauta,'agency_id','') }}"
                        @selected((string)($filters['pauta_id'] ?? '') === (string)data_get($pauta,'value',''))>
                    {{ data_get($pauta,'label','Pauta sin nombre') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="op-filter-control">
        <i class="bi bi-people"></i>
        <label class="visually-hidden" for="siget-unit-filter">Unidades</label>
        <select name="organizational_unit_id" id="siget-unit-filter" aria-label="Unidades">
            <option value="">Todas las unidades</option>
            @foreach($filterUnits as $unit)
                @php
                    $unitId=data_get($unit,'id');
                    $unitName=data_get($unit,'name','');
                    $filterIds=data_get($unit,'filter_unit_ids');
                    $filterIds=is_array($filterIds) ? implode(',',array_map('strval',$filterIds)) : $unitId;
                @endphp
                <option value="{{ $filterIds }}" @selected((string)($filters['organizational_unit_id'] ?? '') === (string)$filterIds)>{{ $unitName }}</option>
            @endforeach
        </select>
    </div>

    <div class="op-filter-control">
        <i class="bi bi-person"></i>
        <label class="visually-hidden" for="siget-responsible-filter">Responsables</label>
        <select name="responsible_id" id="siget-responsible-filter" aria-label="Responsables">
            <option value="">Todos los responsables</option>
            @foreach(($filterResponsibles ?? []) as $responsible)
                <option value="{{ $responsible->id }}" @selected((string)($filters['responsible_id'] ?? '') === (string)$responsible->id)>{{ $responsible->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="op-filter-actions">
        <a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-outline-primary" aria-label="Exportar reporte PDF">
            <i class="bi bi-box-arrow-up-right me-1"></i>Exportar
        </a>
        <button type="submit" class="btn btn-primary" aria-label="Aplicar filtros">
            <i class="bi bi-funnel"></i>
        </button>
        <button type="button" class="op-filter-more" data-bs-toggle="collapse" data-bs-target="#siget-op-advanced-filters" aria-expanded="false">
            <i class="bi bi-sliders"></i><span>Más filtros</span>
        </button>
    </div>
</div>

<div class="collapse" id="siget-op-advanced-filters">
    <div class="op-advanced">
        <div class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Estado de carga</label>
                <select name="status" class="form-select">
                    <option value="">Todos los estados relevantes</option>
                    @foreach($availableStatuses as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Fecha contratada desde</label>
                <input type="month" name="from" id="siget-period-from" value="{{ $selectedFrom }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control">
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Fecha contratada hasta</label>
                <input type="month" name="to" id="siget-period-to" value="{{ $selectedTo }}" min="{{ $periodMin ?? '' }}" max="{{ $periodMax ?? '' }}" class="form-control">
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="small text-secondary">
                    Rango disponible:
                    <strong>{{ $periodMin && $periodMax ? $periodMin.' → '.$periodMax : 'Sin periodo disponible' }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
(function(){
    const form=document.getElementById('siget-dashboard-filters');
    const agency=document.getElementById('siget-agency-filter');
    const pauta=document.getElementById('siget-pauta-filter');
    const from=document.getElementById('siget-period-from');
    const to=document.getElementById('siget-period-to');

    const syncCampaigns=()=>{
        if(!agency||!pauta)return;
        const agencyId=agency.value;
        Array.from(pauta.options).forEach(option=>{
            if(!option.value)return;
            option.hidden=!!agencyId && option.dataset.agencyId && option.dataset.agencyId!==agencyId;
        });
        const selected=pauta.selectedOptions[0];
        if(agencyId && selected?.dataset.agencyId && selected.dataset.agencyId!==agencyId)pauta.value='';
    };

    const syncDates=()=>{
        if(!from||!to)return;
        if(from.value)to.min=from.value;
        if(to.value)from.max=to.value;
    };

    agency?.addEventListener('change',syncCampaigns);
    from?.addEventListener('change',syncDates);
    to?.addEventListener('change',syncDates);
    form?.addEventListener('submit',e=>{
        if(from?.value && to?.value && from.value>to.value){
            e.preventDefault();
            to.setCustomValidity('La fecha contratada final debe ser igual o posterior a la fecha inicial.');
            to.reportValidity();
            to.setCustomValidity('');
        }
    });
    syncCampaigns();
    syncDates();
})();
</script>