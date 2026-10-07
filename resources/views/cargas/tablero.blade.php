@extends('layouts.app')
@section('title', 'Tablero de cargas')
@section('page-title', 'Tablero de cargas por dependencia')
@section('content')
<div class="board-direction-compact">

<div class="siget-board-heading board-heading d-flex justify-content-between align-items-start gap-3">
    <div class="min-w-0">
        <p class="scope-label mb-1">{{ $scopeLabel }}</p>
        <h2 class="mb-1">Tablero de Dirección</h2>
        <p class="mb-0">Seguimiento compacto de cargas, evidencias, fechas y avance dentro del alcance autorizado.</p>
    </div>
    <a href="{{ route('calendar.index') }}" class="btn btn-outline-primary flex-shrink-0"><i class="bi bi-calendar3 me-1"></i> Calendario</a>
</div>

<div class="row g-2 mb-3 board-kpis">

@php $kpis=[
    ['label'=>'Total','value'=>$summary['total'],'icon'=>'bi-collection','type'=>'info'],
    ['label'=>'Por hacer','value'=>$summary['todo'],'icon'=>'bi-list-check','type'=>'primary'],
    ['label'=>'En progreso','value'=>$summary['progress'],'icon'=>'bi-hourglass-split','type'=>'warning'],
    ['label'=>'En revisión','value'=>$summary['review'],'icon'=>'bi-clipboard-check','type'=>'warning'],
    ['label'=>'Cerradas','value'=>$summary['done'],'icon'=>'bi-check2-circle','type'=>'success'],
    ['label'=>'Vencidas','value'=>$summary['overdue'],'icon'=>'bi-calendar-x','type'=>'danger']
]; @endphp
@foreach($kpis as $kpi)
<div><div class="board-kpi">
    <span class="board-kpi-icon text-bg-{{ $kpi['type'] }}"><i class="bi {{ $kpi['icon'] }}"></i></span>
    <small>{{ $kpi['label'] }}</small>
    <strong>{{ $kpi['value'] }}</strong>
</div></div>
@endforeach
</div>



<div class="card siget-card mb-4">
<div class="card-header"><div><h2>Catálogo de dependencias</h2><p>Solo aparecen dependencias con cargas pertenecientes al alcance autorizado. La Pauta identifica el archivo Excel importado por Enlace Institucional.</p></div><span class="badge text-bg-light">Cumplimiento promedio: {{ $summary['completion'] }}%</span></div>
<div class="card-body">
@if($dependencyCards->isEmpty())<div class="text-center py-4 text-secondary">No existen dependencias con cargas dentro de su alcance.</div>
@else<div class="siget-dependency-grid">@foreach($dependencyCards as $card)@php $agency=$card['agency']; $selected=(int)($filters['agency_id']??0)===(int)$agency->id; $url=request()->fullUrlWithQuery(['agency_id'=>$selected?null:$agency->id]); @endphp
<a href="{{ $url }}" class="siget-dependency-card {{ $selected?'active':'' }}"><div class="siget-dependency-top">@if($card['logo_url'])<img src="{{ $card['logo_url'] }}" alt="Logo de {{ $agency->name }}" class="siget-dependency-logo">@else<span class="siget-dependency-logo siget-dependency-initials">{{ $card['initials'] }}</span>@endif<div class="min-w-0"><strong>{{ $agency->name }}</strong><small>{{ $agency->code }}</small></div>@if($selected)<i class="bi bi-check-circle-fill ms-auto"></i>@endif</div><div class="siget-dependency-stats"><span><b>{{ $card['summary']['todo'] }}</b> por hacer</span><span><b>{{ $card['summary']['progress'] }}</b> progreso</span><span><b>{{ $card['summary']['review'] }}</b> revisión</span><span><b>{{ $card['summary']['done'] }}</b> cerradas</span></div><div class="progress mt-3"><div class="progress-bar" style="width: {{ $card['summary']['completion'] }}%"></div></div><div class="d-flex justify-content-between mt-2 small text-secondary"><span>{{ $card['summary']['completion'] }}% avance</span><span>@if($card['next_due']) Próximo: {{ $card['next_due']->format('d/m/Y') }} @else Sin pendientes @endif</span></div></a>
@endforeach</div>@endif
</div></div>

<form method="GET" class="card siget-card mb-4" id="loadBoardFilters">
<div class="card-body">
<div class="row g-3 align-items-end">
<div class="col-lg-3"><label class="form-label">Buscar cargas</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="{{ $filters['q']??'' }}" placeholder="Título, periodo o dependencia"></div></div>
<div class="col-md-4 col-lg-2"><label class="form-label">Pauta (Excel)</label><select name="pauta_id" class="form-select" id="boardPauta"><option value="">Todas las pautas visibles</option>@foreach(($pautas ?? []) as $pauta)<option value="{{ $pauta['id'] }}" data-agency-id="{{ $pauta['agency_id'] }}" @selected((int)($filters['pauta_id']??0)===(int)$pauta['id'])>{{ $pauta['agency'] }} · {{ $pauta['name'] }} · {{ $pauta['load_count'] }} programación(es)</option>@endforeach</select></div><div class="col-md-4 col-lg-2"><label class="form-label">Dirección</label><select name="unit_id" class="form-select"><option value="">{{ in_array(auth()->user()?->role?->code, ['DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'], true) ? 'Toda mi Dirección' : 'Todas las autorizadas' }}</option>@foreach($units as $unit)@php $unitIds=$unit->filter_unit_ids??[(int)$unit->id]; $unitValue=implode(',',array_map('intval',$unitIds)); @endphp<option value="{{ $unitValue }}" @selected((string)($filters['unit_id']??'')===$unitValue)>{{ $unit->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-2"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select" id="boardAgency"><option value="">Todas las dependencias visibles</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((int)($filters['agency_id']??0)===(int)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-3">
    <label class="form-label">Periodo contratado según pauta</label>
    <div class="border rounded px-2 py-1 bg-light-subtle small">
        <i class="bi bi-calendar3 me-1"></i>
        <strong>{{ ($periodBounds['min']??null)&&($periodBounds['max']??null) ? $periodBounds['min'].' → '.$periodBounds['max'] : 'Sin periodo disponible' }}</strong>
    </div>
    <div class="form-text">Se muestra únicamente el periodo disponible para la pauta, dependencia y Dirección visibles.</div>
</div>
@if($canUseMineFilter)<div class="col-md-4 col-lg-1"><div class="form-check form-switch mb-2"><input type="hidden" name="mine" value="0"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mineFilter" @checked($filters['mine']??false)><label class="form-check-label" for="mineFilter">Mías</label></div></div>@endif
<div class="col-md-4 col-lg-1 d-grid"><button class="btn btn-primary" title="Aplicar filtros"><i class="bi bi-funnel"></i></button></div>
<div class="col-md-4 col-lg-1 d-grid"><a href="{{ route('loads.board') }}" class="btn btn-outline-secondary" title="Restablecer"><i class="bi bi-x-lg"></i></a></div>
</div></div>
<div class="board-period-segmented mt-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <strong class="small">Rango segmentado:</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="today">Hoy</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="week">Semana actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="month">Mes actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="quarter">Trimestre actual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary board-range-btn" data-board-range="custom">Personalizado</button>
        <span class="small text-muted ms-auto" id="boardPeriodSummary">{{ ($filters['from']??'')&&($filters['to']??'') ? $filters['from'].' → '.$filters['to'] : (($periodBounds['min']??null)&&($periodBounds['max']??null) ? $periodBounds['min'].' → '.$periodBounds['max'] : 'Periodo completo de la pauta seleccionada') }}</span>
    </div>
    <div class="row g-2 mt-2 d-none" id="boardCustomRange">
        <div class="col-md-3"><label class="form-label">Desde</label><input type="month" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-sm board-custom-from"></div>
        <div class="col-md-3"><label class="form-label">Hasta</label><input type="month" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-sm board-custom-to"></div>
        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary btn-sm mt-auto">Aplicar rango</button></div>
    </div>
</div>
</div></div></form>

<div class="siget-kanban" aria-label="Tablero Kanban de cargas">
@foreach($columns as $key=>$column)<section class="siget-kanban-column siget-kanban-{{ $key }}"><header><div><h3><i class="bi {{ $column['icon'] }} me-1"></i>{{ $column['label'] }}</h3><p>{{ $column['description'] }}</p></div><span>{{ $column['loads']->count() }}</span></header><div class="siget-kanban-stack">
@forelse($column['loads'] as $load)@php $priority=strtoupper((string)($load->priority??'NORMAL')); $traffic=$load->traffic_light instanceof \BackedEnum?$load->traffic_light->value:(string)$load->traffic_light; @endphp
<article class="siget-load-card {{ $load->board_overdue?'is-overdue':'' }}">
    <div class="load-card-top">
        <div class="load-card-title">
            <span class="siget-load-agency">{{ $load->agency?->name }}</span>
            <h4 title="{{ $load->title }}">{{ $load->title }}</h4>
        </div>
        <span class="siget-traffic siget-traffic-{{ strtolower($traffic?:'gray') }}" title="Semáforo {{ $traffic }}"></span>
    </div>
    <div class="load-card-badges">
        <span class="badge siget-status">{{ $load->board_status_label }}</span>
        <span class="badge siget-priority siget-priority-{{ strtolower($priority) }}">{{ $priority }}</span>
        @if($load->board_overdue)<span class="badge text-bg-danger">Vencida</span>@endif
        @if($load->is_blocked)<span class="badge text-bg-secondary">Bloqueada</span>@endif
    </div>
    @if($load->board_unit_names->isNotEmpty())
        <div class="siget-load-units">
            @foreach($load->board_unit_names as $unitName)
                <span><i class="bi bi-diagram-3"></i> {{ $unitName }}</span>
            @endforeach
        </div>
    @endif
    <div class="siget-load-meta">
        <span><i class="bi bi-calendar-event"></i> {{ $load->effective_open_at?->format('d/m/Y')??'Sin fecha' }}</span>
        <span><i class="bi bi-alarm"></i> {{ $load->effective_close_at?->format('d/m/Y H:i')??'Sin límite' }}</span>
    </div>
    <div class="load-card-progress-label">
        <span>Avance visible</span>
        <strong>{{ number_format($load->board_progress,0) }}%</strong>
    </div>
    <div class="progress load-card-progress"><div class="progress-bar" style="width: {{ $load->board_progress }}%"></div></div>
    <div class="siget-load-footer">
        <div class="siget-load-counters">
            <span title="Entregables"><i class="bi bi-check2-square"></i> {{ $load->deliverables->count() }}</span>
            <span title="Evidencias"><i class="bi bi-paperclip"></i> {{ $load->board_evidence_count }}</span>
            <span title="Observaciones"><i class="bi bi-chat-left-text"></i> {{ $load->board_observation_count }}</span>
        </div>
        <a href="{{ route('loads.show',$load) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
    </div>
    @if($load->board_responsibles->isNotEmpty())
        <div class="siget-assignees"><i class="bi bi-person-check"></i> {{ $load->board_responsibles->join(', ') }}</div>
    @endif
</article>
@empty<div class="siget-kanban-empty"><i class="bi {{ $column['icon'] }}"></i><span>No hay cargas en esta etapa.</span></div>@endforelse
</div></section>@endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('loadBoardFilters');
 const agency=document.getElementById('boardAgency');
 const pauta=document.getElementById('boardPauta');
 const from=form?.querySelector('[name="from"]'),to=form?.querySelector('[name="to"]');
 const custom=document.getElementById('boardCustomRange');
 const summary=document.getElementById('boardPeriodSummary');
 const min='{{ $periodBounds['min'] ?? '' }}',max='{{ $periodBounds['max'] ?? '' }}';

 const syncPautas=()=>{
   if(!agency||!pauta)return;
   const agencyId=agency.value;
   Array.from(pauta.options).forEach(option=>{
      if(!option.value)return;
      option.hidden=!!agencyId && option.dataset.agencyId && option.dataset.agencyId!==agencyId;
   });
   const selected=pauta.selectedOptions[0];
   if(agencyId&&selected?.dataset.agencyId&&selected.dataset.agencyId!==agencyId)pauta.value='';
 };

 const syncMonths=()=>{
   if(!from||!to)return;
   from.min=min; from.max=max; to.min=min; to.max=max;
   if(from.value)to.min=from.value;
   if(to.value)from.max=to.value;
   if(from.value&&to.value&&from.value>to.value)to.value=from.value;
   if(summary)summary.textContent=from.value&&to.value ? from.value+' → '+to.value : (min&&max ? min+' → '+max : 'Periodo completo de la pauta seleccionada');
 };

 const applyPreset=(range)=>{
   const now=new Date(), year=now.getFullYear();
   let start=now.getMonth()+1, end=start;
   if(range==='quarter') start=Math.floor(now.getMonth()/3)*3+1;
   const startValue=year+'-'+String(start).padStart(2,'0');
   const endValue=year+'-'+String(end).padStart(2,'0');
   if(from)from.value=startValue;
   if(to)to.value=endValue;
   custom?.classList.add('d-none');
   syncMonths();
   form?.submit();
 };

 document.querySelectorAll('.board-range-btn').forEach(button=>{
   button.addEventListener('click',()=>{
     document.querySelectorAll('.board-range-btn').forEach(x=>x.classList.remove('active'));
     button.classList.add('active');
     if(button.dataset.boardRange==='custom'){
       custom?.classList.remove('d-none');
       return;
     }
     applyPreset(button.dataset.boardRange);
   });
 });

 agency?.addEventListener('change',syncPautas);
 pauta?.addEventListener('change',()=>custom?.classList.remove('d-none'));
 from?.addEventListener('change',syncMonths);
 to?.addEventListener('change',syncMonths);
 syncPautas();
 syncMonths();
 if(from?.value||to?.value)custom?.classList.remove('d-none');
});
</script>

</div>
@endsection
