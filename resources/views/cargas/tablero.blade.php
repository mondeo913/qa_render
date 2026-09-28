@extends('layouts.app')
@section('title', 'Tablero de cargas')
@section('page-title', 'Tablero de cargas por dependencia')
@section('content')
<div class="board-direction-compact">

<style>
/* Estándar compacto del tablero para Directores: misma geometría de KPI que el dashboard. */
.board-direction-compact{padding-top:0}
.board-direction-compact .board-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin:0 0 16px}
.board-direction-compact .board-kpis>[class*="col-"]{width:auto!important;max-width:none!important;padding-left:0;padding-right:0}
.board-direction-compact .board-kpi{min-height:72px;height:72px;padding:8px 9px;display:grid;grid-template-columns:28px 1fr;grid-template-rows:auto 1fr;column-gap:8px;align-items:center;background:#121b24;border:1px solid rgba(255,255,255,.08);border-radius:12px;color:#fff}
.board-direction-compact .board-kpi .board-kpi-icon{grid-row:1 / span 2;width:28px;height:28px;border-radius:8px;display:grid;place-items:center;font-size:.82rem}
.board-direction-compact .board-kpi small{font-size:.62rem;line-height:1.05;color:#91a5b7;white-space:normal;overflow-wrap:anywhere}
.board-direction-compact .board-kpi strong{font-size:1.05rem;line-height:1;color:#fff}
.board-direction-compact .board-heading{margin-bottom:12px!important}
.board-direction-compact .board-heading .scope-label{font-size:.65rem;color:#91a5b7}
.board-direction-compact .board-heading h2{font-size:1rem;color:#fff;margin-bottom:2px!important}
.board-direction-compact .board-heading p{font-size:.68rem;color:#91a5b7}
.board-direction-compact .board-heading .btn{height:30px;padding:.25rem .55rem;font-size:.68rem}
@media(max-width:1200px){.board-direction-compact .board-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.board-direction-compact .board-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.board-direction-compact .board-kpi{height:68px;min-height:68px}}
</style>

<div class="siget-board-heading board-heading d-flex justify-content-between align-items-start gap-3">
    <div class="min-w-0">
        <p class="scope-label mb-1">{{ $scopeLabel }}</p>
        <h2 class="mb-1">Tablero de Dirección</h2>
        <p class="mb-0">Seguimiento compacto de cargas, evidencias, fechas y avance dentro del alcance autorizado.</p>
    </div>
    <a href="{{ route('calendar.index') }}" class="btn btn-outline-primary flex-shrink-0"><i class="bi bi-calendar3 me-1"></i> Calendario</a>
</div>

<div class="row g-2 mb-3 board-kpis">

<div class="row g-3 mb-4">
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
<div class="card-header"><div><h2>Catálogo de dependencias</h2><p>Seleccione una dependencia para ver solamente sus cargas dentro del alcance autorizado.</p></div><span class="badge text-bg-light">Cumplimiento promedio: {{ $summary['completion'] }}%</span></div>
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
<div class="col-md-4 col-lg-2"><label class="form-label">Dirección</label><select name="unit_id" class="form-select"><option value="">Todas autorizadas</option>@foreach($units as $unit)@php $unitIds=$unit->filter_unit_ids??[(int)$unit->id]; $unitValue=implode(',',array_map('intval',$unitIds)); @endphp<option value="{{ $unitValue }}" @selected((string)($filters['unit_id']??'')===$unitValue)>{{ $unit->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-2"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select" id="boardAgency"><option value="">Todas</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((int)($filters['agency_id']??0)===(int)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
<div class="col-md-4 col-lg-3"><label class="form-label">Periodo contratado</label><div class="input-group"><span class="input-group-text"><i class="bi bi-calendar3"></i></span><input type="month" name="from" id="boardFrom" class="form-control" value="{{ $filters['from']??'' }}" min="{{ $periodBounds['min']??'' }}" max="{{ $periodBounds['max']??'' }}" title="Mes inicial"><span class="input-group-text">a</span><input type="month" name="to" id="boardTo" class="form-control" value="{{ $filters['to']??'' }}" min="{{ $periodBounds['min']??'' }}" max="{{ $periodBounds['max']??'' }}" title="Mes final"></div><div class="form-text">Seleccione un rango de meses conforme a los periodos contratados de la pauta.</div></div>
@if($canUseMineFilter)<div class="col-md-4 col-lg-1"><div class="form-check form-switch mb-2"><input type="hidden" name="mine" value="0"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mineFilter" @checked($filters['mine']??false)><label class="form-check-label" for="mineFilter">Mías</label></div></div>@endif
<div class="col-md-4 col-lg-1 d-grid"><button class="btn btn-primary" title="Aplicar filtros"><i class="bi bi-funnel"></i></button></div>
<div class="col-md-4 col-lg-1 d-grid"><a href="{{ route('loads.board') }}" class="btn btn-outline-secondary" title="Restablecer"><i class="bi bi-x-lg"></i></a></div>
</div></div></form>

<div class="siget-kanban" aria-label="Tablero Kanban de cargas">
@foreach($columns as $key=>$column)<section class="siget-kanban-column siget-kanban-{{ $key }}"><header><div><h3><i class="bi {{ $column['icon'] }} me-1"></i>{{ $column['label'] }}</h3><p>{{ $column['description'] }}</p></div><span>{{ $column['loads']->count() }}</span></header><div class="siget-kanban-stack">
@forelse($column['loads'] as $load)@php $priority=strtoupper((string)($load->priority??'NORMAL')); $traffic=$load->traffic_light instanceof \BackedEnum?$load->traffic_light->value:(string)$load->traffic_light; @endphp
<article class="siget-load-card {{ $load->board_overdue?'is-overdue':'' }}"><div class="d-flex justify-content-between gap-2 align-items-start"><div><span class="siget-load-agency">{{ $load->agency?->name }}</span><h4>{{ $load->title }}</h4></div><span class="siget-traffic siget-traffic-{{ strtolower($traffic?:'gray') }}" title="Semáforo {{ $traffic }}"></span></div><div class="d-flex flex-wrap gap-1 mb-2"><span class="badge siget-status">{{ $load->board_status_label }}</span><span class="badge siget-priority siget-priority-{{ strtolower($priority) }}">{{ $priority }}</span>@if($load->board_overdue)<span class="badge text-bg-danger">Vencida</span>@endif @if($load->is_blocked)<span class="badge text-bg-secondary">Bloqueada</span>@endif</div>@if($load->board_unit_names->isNotEmpty())<div class="siget-load-units">@foreach($load->board_unit_names as $unitName)<span><i class="bi bi-diagram-3"></i> {{ $unitName }}</span>@endforeach</div>@endif<div class="siget-load-meta"><span><i class="bi bi-calendar-event"></i> {{ $load->effective_open_at?->format('d/m/Y')??'Sin fecha' }}</span><span><i class="bi bi-alarm"></i> {{ $load->effective_close_at?->format('d/m/Y H:i')??'Sin límite' }}</span></div><div class="d-flex justify-content-between small mt-3 mb-1"><span>Avance visible</span><strong>{{ number_format($load->board_progress,0) }}%</strong></div><div class="progress"><div class="progress-bar" style="width: {{ $load->board_progress }}%"></div></div><div class="siget-load-footer"><div class="siget-load-counters"><span title="Entregables"><i class="bi bi-check2-square"></i> {{ $load->deliverables->count() }}</span><span title="Evidencias"><i class="bi bi-paperclip"></i> {{ $load->board_evidence_count }}</span><span title="Observaciones"><i class="bi bi-chat-left-text"></i> {{ $load->board_observation_count }}</span></div><a href="{{ route('loads.show',$load) }}" class="btn btn-sm btn-outline-primary">Abrir</a></div>@if($load->board_responsibles->isNotEmpty())<div class="siget-assignees mt-2"><i class="bi bi-person-check"></i> {{ $load->board_responsibles->join(', ') }}</div>@endif</article>
@empty<div class="siget-kanban-empty"><i class="bi {{ $column['icon'] }}"></i><span>No hay cargas en esta etapa.</span></div>@endforelse
</div></section>@endforeach
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const from=document.getElementById('boardFrom'),to=document.getElementById('boardTo');
 if(!from||!to)return;
 const sync=()=>{if(from.value)to.min=from.value;else to.min=from.min||'';if(to.value)from.max=to.value;else from.max=from.max||'';if(from.value&&to.value&&from.value>to.value)to.value=from.value;};
 from.addEventListener('change',sync);to.addEventListener('change',sync);sync();
});
</script>
@endsection
