@extends('layouts.app')
@section('title','Dashboard SIGET')
@section('page-title', $presentation['title'])
@section('page-subtitle', $presentation['subtitle'])
@section('content')
@php
    $k = $analytics['kpis'] ?? [];
    $filters = $filters ?? [];
@endphp
<section class="siget-hero mb-4">
    <span class="badge text-bg-primary mb-2">{{ $presentation['scope'] ?? 'Vista institucional' }}</span>
    <h2>Dashboard ejecutivo SIGET</h2>
    <p>Control de avance, carga operativa, riesgo, oportunidad y trazabilidad. Los resultados respetan el alcance seleccionado.</p>
</section>
<form method="GET" class="card siget-card mb-4">
    <div class="card-header"><div><h2>Contexto de análisis</h2><p>Primero define el universo; después interpreta los indicadores.</p></div></div>
    <div class="card-body row g-3 align-items-end">
        <div class="col-xl-3 col-md-6"><label class="form-label">Dependencia</label><select name="agency_id" class="form-select"><option value="">Todas las dependencias</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(($filters['agency_id'] ?? null) == $agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
        <div class="col-xl-3 col-md-6"><label class="form-label">Dirección / unidad</label><select name="organizational_unit_id" class="form-select"><option value="">Todas las direcciones / unidades</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(($filters['organizational_unit_id'] ?? null) == $unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Estado</label><select name="status" class="form-select"><option value="">Todos</option>@foreach(['PROGRAMADA','ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','VALIDADA','VALIDADO_Y_CERRADO','VENCIDA','REPROGRAMADA'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) == $status)>{{ str_replace('_',' ',$status) }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Desde</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Aplicar filtros</button>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Restablecer</a>
            <a href="{{ route('indicators.index', request()->query()) }}" class="btn btn-outline-primary">Análisis de indicadores</a>
            <a href="{{ route('intelligence', request()->query()) }}" class="btn btn-outline-primary ms-auto"><i class="bi bi-graph-up-arrow me-1"></i>Centro de Inteligencia</a>
        </div>
    </div>
</form>
<div class="row g-3 mb-4">
    @foreach([
        ['Avance medio',$k['completion_average'] ?? 0,'%','bi-activity','primary'],
        ['Cargas activas',$k['active'] ?? 0,'','bi-broadcast-pin','info'],
        ['Pendientes de revisión',$k['review_pending'] ?? 0,'','bi-clipboard-check','warning'],
        ['Próximas 72 h',$k['due_soon'] ?? 0,'','bi-alarm','warning'],
        ['Reprogramadas',$k['reprogrammed'] ?? 0,'','bi-arrow-repeat','secondary'],
        ['Con observación',$k['observed'] ?? 0,'','bi-chat-square-exclamation','danger']
    ] as [$label,$value,$suffix,$icon,$type])
        <div class="col-6 col-xl-2"><div class="siget-kpi"><div class="siget-kpi-icon text-bg-{{ $type }}"><i class="bi {{ $icon }}"></i></div><div><small>{{ $label }}</small><strong>{{ $value }}{{ $suffix }}</strong></div></div></div>
    @endforeach
</div>
<div class="row g-4">
    <div class="col-xl-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Ritmo de operación</h2><p>Entradas, cierres y capacidad de respuesta por periodo.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="monthlyTrendChart"></canvas></div></div></div></div>
    <div class="col-xl-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Embudo de entregables</h2><p>Del trabajo programado a la validación/cierre.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="funnelChart"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="card siget-card h-100"><div class="card-header"><div><h2>Capacidad por dirección</h2><p>Volumen y porcentaje validado de entregables.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="directionChart"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="card siget-card h-100"><div class="card-header"><div><h2>Riesgo y oportunidad</h2><p>Vencimientos y concentración de cargas por dependencia.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="agencyRiskChart"></canvas></div></div></div></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-xl-7"><div class="card siget-card"><div class="card-header"><div><h2>Atención ejecutiva</h2><p>Casos que requieren decisión, seguimiento o corrección.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('intelligence', request()->query()) }}">Analizar</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Situación</th><th>Dependencia</th><th>Registro</th><th>Fecha</th></tr></thead><tbody>@forelse($analytics['risk_items'] ?? [] as $item)<tr><td><span class="badge text-bg-{{ (($item->status instanceof \BackedEnum ? $item->status->value : $item->status) === 'VENCIDA') ? 'danger' : 'warning' }}">{{ $item->status instanceof \BackedEnum ? $item->status->value : $item->status }}</span></td><td>{{ $item->agency?->name }}</td><td><a href="{{ route('loads.show',$item) }}"><strong>{{ $item->title }}</strong></a></td><td>{{ $item->effective_close_at?->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Sin casos críticos en el alcance.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="col-xl-5"><div class="card siget-card"><div class="card-header"><div><h2>Trazabilidad documental</h2><p>Evidencias vinculadas al universo seleccionado.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="evidenceChart"></canvas></div></div></div></div>
</div>
<script type="application/json" data-siget-chart="monthlyTrendChart">{!! json_encode(['type'=>'line','labels'=>collect($analytics['monthly_trend'] ?? [])->pluck('period'),'datasets'=>[['label'=>'Entradas','data'=>collect($analytics['monthly_trend'] ?? [])->pluck('total')],['label'=>'Cierres','data'=>collect($analytics['monthly_trend'] ?? [])->pluck('closed')],['label'=>'Cumplimiento %','data'=>collect($analytics['monthly_trend'] ?? [])->pluck('compliance'),'yAxisID'=>'y1']]]) !!}</script>
<script type="application/json" data-siget-chart="funnelChart">{!! json_encode(['type'=>'bar','labels'=>array_keys($analytics['deliverable_funnel'] ?? []),'datasets'=>[['label'=>'Entregables','data'=>array_values($analytics['deliverable_funnel'] ?? [])]]]) !!}</script>
<script type="application/json" data-siget-chart="directionChart">{!! json_encode(['type'=>'bar','labels'=>collect($analytics['unit_performance'] ?? [])->pluck('unit'),'datasets'=>[['label'=>'Entregables','data'=>collect($analytics['unit_performance'] ?? [])->pluck('total')],['label'=>'Validados','data'=>collect($analytics['unit_performance'] ?? [])->pluck('validated')]]]) !!}</script>
<script type="application/json" data-siget-chart="agencyRiskChart">{!! json_encode(['type'=>'bar','labels'=>collect($analytics['agency_performance'] ?? [])->pluck('agency'),'datasets'=>[['label'=>'Vencidas','data'=>collect($analytics['agency_performance'] ?? [])->pluck('overdue')],['label'=>'Cumplimiento %','data'=>collect($analytics['agency_performance'] ?? [])->pluck('percentage')]]) !!}</script>
<script type="application/json" data-siget-chart="evidenceChart">{!! json_encode(['type'=>'doughnut','labels'=>array_keys($analytics['evidence_funnel'] ?? []),'datasets'=>[['label'=>'Evidencias','data'=>array_values($analytics['evidence_funnel'] ?? [])]]]) !!}</script>
@endsection
