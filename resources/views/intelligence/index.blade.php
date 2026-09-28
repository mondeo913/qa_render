@extends('layouts.app')
@section('title','Centro de Inteligencia')
@section('page-title',$analytics['role']['title'] ?? 'Centro de Inteligencia')
@section('page-subtitle',$analytics['role']['subtitle'] ?? 'Lectura operativa y de riesgo con el mismo universo autorizado para su perfil.')
@section('content')
@php
    $k = $analytics['kpis'] ?? [];
    $filters = $filters ?? [];
    $attention = collect($analytics['attention_items'] ?? []);
    $quality = collect($analytics['quality_items'] ?? []);
    $agencyRows = collect($analytics['agency_performance'] ?? []);
    $directions = collect($analytics['direction_performance'] ?? []);
    $pautaRows = collect($analytics['pauta_performance'] ?? []);
    $pautas = collect($pautas ?? []);
    $statusOptions = $statusOptions ?? [];
    $radar = $analytics['radar_summary'] ?? ['CRÍTICO'=>0,'ATENCIÓN'=>0,'NORMAL'=>0];
    $health = $analytics['system_health'] ?? null;
    $directionScope = $analytics['direction_scope'] ?? null;
    $isDirectionDirector = in_array(auth()->user()?->role?->code, ['DIRECTOR','DIRECTOR_TRANSMISION','DIRECTOR_PROGRAMACION_CONTINUIDAD'], true);
@endphp

<section class="siget-hero mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <span class="badge text-bg-primary mb-2">{{ $analytics['role']['code'] ?? 'SIGET' }}</span>
            <h2 class="mb-1">Radar y alertas</h2>
            <p class="mb-0">Esta vista no modifica cargas, evidencias ni estados. En una Dirección, toda la lectura queda restringida exclusivamente a la Dirección autorizada y a la información de sus unidades subordinadas.</p>
        </div>
        <div class="text-end"><small class="d-block text-muted">Actualizado</small><strong>{{ optional($analytics['generated_at'] ?? null)->format('d/m/Y H:i') }}</strong></div>
    </div>
</section>

<form method="GET" class="card siget-card mb-4">
    <div class="card-header"><div><h2>Filtros de lectura ejecutiva</h2><p>La lectura se clasifica primero por <strong>pauta</strong> y <strong>dependencia</strong>; el estado conserva únicamente las categorías ejecutivas principales.</p></div></div>
    <div class="card-body row g-3 align-items-end">
        <div class="col-xl-4 col-md-6"><label class="form-label">Pauta</label><select name="pauta_id" class="form-select"><option value="">Todas las pautas visibles</option>@foreach($pautas as $pauta)<option value="{{ $pauta['id'] }}" @selected((string)($filters['pauta_id']??'') === (string)$pauta['id'])>{{ $pauta['name'] }} · {{ $pauta['agency'] }}</option>@endforeach</select></div>
        <div class="col-xl-3 col-md-6"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select"><option value="">Todas las visibles</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency_id']??'') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
        <div class="col-xl-3 col-md-6"><label class="form-label">Dirección</label><select name="organizational_unit_id" class="form-select"><option value="">Todas las visibles</option>@foreach($units as $unit)@php $unitIdList=implode(',',array_map('strval',(array)($unit->filter_unit_ids??[$unit->id]))); @endphp<option value="{{ $unitIdList }}" @selected((string)($filters['organizational_unit_id']??'') === $unitIdList)>{{ $unit->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-6"><label class="form-label">Estado ejecutivo</label><select name="status" class="form-select"><option value="">Todos los principales</option>@foreach($statusOptions as $status=>$definition)<option value="{{ $status }}" @selected(($filters['status']??null)===$status)>{{ $definition['label'] }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Desde</label><input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Actualizar lectura</button><a href="{{ route('intelligence') }}" class="btn btn-outline-secondary">Restablecer</a></div>
    </div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Atención prioritaria',$k['attention_total']??0,'Casos que requieren actuar','danger','bi-bullseye'],
        ['Vencidas',$k['overdue']??0,'Fuera de fecha','danger','bi-calendar-x'],
        ['Próximas 72 h',$k['due_soon']??0,'Con fecha crítica cercana','warning','bi-alarm'],
        ['En revisión',$k['review_queue']??0,'Entregables pendientes de revisión','info','bi-clipboard-check'],
        ['Evidencias faltantes',$k['missing_evidence']??0,'Requisitos sin evidencia','secondary','bi-file-earmark-x'],
        ['Reprogramadas',$k['reprogrammed']??0,'Cargas reprogramadas','primary','bi-arrow-repeat'],
    ] as [$label,$value,$hint,$type,$icon])
        <div class="col-6 col-xl-2"><div class="siget-kpi h-100"><div class="siget-kpi-icon text-bg-{{ $type }}"><i class="bi {{ $icon }}"></i></div><div><small>{{ $label }}</small><strong>{{ number_format((int)$value) }}</strong><span class="d-block small text-muted mt-1">{{ $hint }}</span></div></div></div>
    @endforeach
</div>

@if($health)
<div class="card siget-card mb-4"><div class="card-header"><div><h2>Salud de configuración</h2><p>Lectura administrativa sin intervenir la operación.</p></div></div><div class="card-body"><div class="row g-3">
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Usuarios activos</small><strong class="fs-4 d-block">{{ number_format($health['active_users']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Dependencias visibles</small><strong class="fs-4 d-block">{{ number_format($health['active_agencies']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Direcciones / unidades visibles</small><strong class="fs-4 d-block">{{ number_format($health['active_units']) }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="border rounded p-3 h-100"><small class="text-muted">Usuarios sin alcance</small><strong class="fs-4 d-block {{ $health['users_without_scope'] > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($health['users_without_scope']) }}</strong></div></div>
</div></div></div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-8"><div class="card siget-card h-100"><div class="card-header"><div><h2>Atención prioritaria</h2><p>Ordenada por vencimiento, observaciones y pendientes de revisión.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nivel</th><th>Situación</th><th>Alcance</th><th>Fecha</th><th></th></tr></thead><tbody>
        @forelse($attention as $item)
            <tr><td><span class="badge {{ $item['level']==='CRÍTICO'?'text-bg-danger':'text-bg-warning' }}">{{ $item['level'] }}</span></td><td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['detail'] }}</small></td><td>{{ $item['description'] }}</td><td>{{ !empty($item['date']) ? \Illuminate\Support\Carbon::parse($item['date'])->format('d/m/Y') : '—' }}</td><td class="text-end">@if(!empty($item['load_id']))<a href="{{ route('loads.show',$item['load_id']) }}" class="btn btn-sm btn-outline-primary">Ver carga</a>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center py-4">No hay situaciones prioritarias dentro del universo seleccionado.</td></tr>@endforelse
    </tbody></table></div></div></div>
    <div class="col-xl-4"><div class="card siget-card h-100"><div class="card-header"><div><h2>{{ $isDirectionDirector ? 'Radar de '.$directionScope['name'] : 'Radar' }}</h2><p>{{ $isDirectionDirector ? 'Riesgo consolidado exclusivamente de la Dirección autorizada.' : 'Distribución de dependencias visibles por nivel de atención.' }}</p></div></div><div class="card-body">
        @php $radarBase=max(1,$isDirectionDirector ? $directions->count() : (int)$agencies->count()); @endphp
        @foreach([['CRÍTICO',$radar['CRÍTICO']??0,'danger'],['ATENCIÓN',$radar['ATENCIÓN']??0,'warning'],['NORMAL',$radar['NORMAL']??0,'success']] as [$label,$value,$type])
            @php $width=min(100,round(100*$value/$radarBase)); @endphp
            <div class="mb-4"><div class="d-flex justify-content-between mb-1"><strong>{{ $label }}</strong><span>{{ $value }}</span></div><div class="progress" style="height:10px"><div class="progress-bar bg-{{ $type }}" style="width:{{ $width }}%"></div></div></div>
        @endforeach
        <div class="small text-muted">La clasificación se calcula con cargas vencidas, vencimientos en 72 horas y porcentaje de cierre del universo visible.</div>
    </div></div></div>
</div>

<div class="card siget-card mb-4">
    <div class="card-header"><div><h2>{{ $isDirectionDirector ? "Clasificación ejecutiva por Pauta y Dirección" : "Clasificación ejecutiva por Pauta y Dependencia" }}</h2><p>Consolidado institucional: <strong>programadas, reprogramadas, en revisión, validadas, cerradas y vencidas</strong>. La vista evita exponer estados internos de operación que no aportan a la lectura ejecutiva.</p></div></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pauta</th><th>Dependencia</th><th>Programadas</th><th>Reprogramadas</th><th>En revisión</th><th>Validadas</th><th>Cerradas</th><th>Vencidas</th></tr></thead><tbody>
    @forelse($pautaRows as $row)
        <tr>
            <td><strong>{{ $row['pauta_name'] }}</strong><small class="d-block text-muted">{{ number_format($row['total']) }} cargas</small></td>
            <td>{{ $row['agency'] }}</td>
            <td>{{ number_format($row['programmed']) }}</td>
            <td>{{ number_format($row['reprogrammed']) }}</td>
            <td><span class="badge {{ $row['in_review'] > 0 ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ number_format($row['in_review']) }}</span></td>
            <td><span class="badge text-bg-info">{{ number_format($row['validated']) }}</span></td>
            <td><span class="badge text-bg-success">{{ number_format($row['closed']) }}</span></td>
            <td><span class="badge {{ $row['overdue'] > 0 ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ number_format($row['overdue']) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center py-4">No hay pautas dentro del alcance seleccionado.</td></tr>
    @endforelse
    </tbody></table></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Riesgo por Dirección</h2><p>{{ $isDirectionDirector ? "Solo ".$directionScope["name"].". Se consolidan únicamente sus unidades y entregables autorizados." : "El riesgo se limita a las Direcciones que el usuario tiene autorizadas." }}</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Dirección</th><th>Cargas</th><th>Cierre</th><th>Venc.</th><th>72 h</th><th>Estado</th></tr></thead><tbody>@forelse($directions->take(10) as $row)<tr><td><strong>{{ $row['name'] ?? 'Sin unidad' }}</strong></td><td>{{ $row['total'] }}</td><td>{{ $row['percentage'] }}%</td><td>{{ $row['overdue'] }}</td><td>{{ $row['due_soon'] ?? 0 }}</td><td><span class="badge {{ ($row['overdue']??0)>0?'text-bg-danger':(($row['percentage']??0)<80?'text-bg-warning':'text-bg-success') }}">{{ ($row['overdue']??0)>0?'CRÍTICO':(($row['percentage']??0)<80?'ATENCIÓN':'NORMAL') }}</span></td></tr>@empty<tr><td colspan="6" class="text-center py-4">Sin información.</td></tr>@endforelse</tbody></table></div></div></div>
@unless($isDirectionDirector)
    <div class="col-xl-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Riesgo por Dependencia</h2><p>Concentración de vencimientos y avance.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Dependencia</th><th>Cierre</th><th>Venc.</th><th>72 h</th><th></th></tr></thead><tbody>@forelse($agencyRows->take(8) as $row)<tr><td><strong>{{ $row['name'] ?? 'Sin dependencia' }}</strong></td><td>{{ $row['percentage'] }}%</td><td>{{ $row['overdue'] }}</td><td>{{ $row['due_soon'] ?? 0 }}</td><td><span class="badge {{ ($row['overdue']??0)>0?'text-bg-danger':(($row['percentage']??0)<80?'text-bg-warning':'text-bg-success') }}">{{ ($row['overdue']??0)>0?'CRÍTICO':(($row['percentage']??0)<80?'ATENCIÓN':'NORMAL') }}</span></td></tr>@empty<tr><td colspan="5" class="text-center py-4">Sin información.</td></tr>@endforelse</tbody></table></div></div></div>
@endunless
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Inconsistencias y calidad</h2><p>Excepciones que pueden impedir una revisión o cierre limpio.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nivel</th><th>Hallazgo</th><th>Detalle</th><th></th></tr></thead><tbody>@forelse($quality as $item)<tr><td><span class="badge {{ $item['level']==='CRÍTICO'?'text-bg-danger':'text-bg-warning' }}">{{ $item['level'] }}</span></td><td><strong>{{ $item['title'] }}</strong><small class="d-block text-muted">{{ $item['description'] }}</small></td><td>{{ $item['detail'] }}</td><td class="text-end">@if(!empty($item['evidence_id']))<a href="{{ route('evidences.show',$item['evidence_id']) }}" class="btn btn-sm btn-outline-primary">Ver evidencia</a>@elseif(!empty($item['load_id']))<a href="{{ route('loads.show',$item['load_id']) }}" class="btn btn-sm btn-outline-primary">Ver carga</a>@endif</td></tr>@empty<tr><td colspan="4" class="text-center py-4">No se detectaron inconsistencias dentro del universo seleccionado.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-xl-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Lectura rápida</h2><p>Qué significa la situación actual de este usuario.</p></div></div><div class="card-body"><div class="vstack gap-3">
        <div><small class="text-muted">Cumplimiento</small><strong class="d-block fs-5">{{ $k['compliance']??0 }}%</strong><span class="small text-muted">Cargas cerradas sobre el universo visible.</span></div>
        <div><small class="text-muted">Capacidad de respuesta</small><strong class="d-block fs-5">{{ $k['active']??0 }} en operación</strong><span class="small text-muted">Cargas aún dentro del flujo activo.</span></div>
        <div><small class="text-muted">Riesgo inmediato</small><strong class="d-block fs-5">{{ $k['overdue']??0 }} vencidas · {{ $k['due_soon']??0 }} en 72 h</strong><span class="small text-muted">Priorizar por fecha y estado.</span></div>
        <div><small class="text-muted">Calidad</small><strong class="d-block fs-5">{{ $k['missing_evidence']??0 }} faltantes · {{ $k['stale_reviews']??0 }} revisiones atrasadas</strong><span class="small text-muted">Excepciones detectadas sin modificar registros.</span></div>
    </div></div></div></div>
</div>

<div class="card siget-card"><div class="card-header"><div><h2>Tendencia institucional</h2><p>Comparación mensual del volumen contra cierres dentro del universo seleccionado.</p></div></div><div class="card-body"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Periodo</th><th>Cargas</th><th>Cierres</th><th>Cumplimiento</th><th>Lectura</th></tr></thead><tbody>@forelse($analytics['monthly_trend'] ?? [] as $row)<tr><td>{{ $row['period'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['closed'] }}</td><td>{{ $row['compliance'] }}%</td><td><div class="progress" style="height:7px;min-width:120px"><div class="progress-bar" style="width:{{ min(100,(float)$row['compliance']) }}%"></div></div></td></tr>@empty<tr><td colspan="5" class="text-center py-4">Sin histórico suficiente con los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
