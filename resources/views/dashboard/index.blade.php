@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', $presentation['title'])
@section('page-subtitle', $presentation['subtitle'])
@section('content')
@php
    $k = $analytics['kpis'];
    $r = auth()->user()->role?->code;
    $enlace = $r === 'ENLACE_INSTITUCIONAL';
    $operador = in_array($r, ['OPERADOR', 'OPERADOR_TRANSMISION', 'OPERADOR_PROGRAMACION_CONTINUIDAD'], true);

    if ($enlace) {
        $cards = [
            ['Pendientes de revisión', $k['review_pending'], 'bi-clipboard-check', 'warning'],
            ['Observadas', $k['observed'], 'bi-chat-square-text', 'danger'],
            ['Cerradas', $k['closed'], 'bi-patch-check', 'success'],
            ['Vencen en 3 días', $k['due_soon'], 'bi-alarm', 'info'],
            ['Vencidas', $k['overdue'], 'bi-exclamation-octagon', 'danger'],
            ['Cumplimiento', $k['compliance'].'%', 'bi-speedometer2', 'primary'],
        ];
    } elseif ($operador) {
        $cards = [
            ['Cargas asignadas', $k['total'], 'bi-collection', 'primary'],
            ['En operación', $k['active'], 'bi-broadcast-pin', 'info'],
            ['Observadas', $k['observed'], 'bi-chat-square-text', 'warning'],
            ['Vencen en 3 días', $k['due_soon'], 'bi-alarm', 'danger'],
            ['Cerradas', $k['closed'], 'bi-patch-check', 'success'],
            ['Avance promedio', $k['completion_average'].'%', 'bi-bar-chart-line', 'primary'],
        ];
    } else {
        $cards = [
            ['Total', $k['total'], 'bi-collection', 'primary'],
            ['Activas', $k['active'], 'bi-broadcast-pin', 'info'],
            ['En revisión', $k['review_pending'], 'bi-clipboard-check', 'warning'],
            ['Cerradas', $k['closed'], 'bi-patch-check', 'success'],
            ['Vencidas', $k['overdue'], 'bi-exclamation-octagon', 'danger'],
            ['Cumplimiento', $k['compliance'].'%', 'bi-speedometer2', 'primary'],
        ];
    }

    $monthlyChart = [
        'type' => 'line',
        'labels' => collect($analytics['monthly_trend'])->pluck('period')->values(),
        'datasets' => [
            ['label' => 'Programadas', 'data' => collect($analytics['monthly_trend'])->pluck('total')->values()],
            ['label' => 'Cerradas', 'data' => collect($analytics['monthly_trend'])->pluck('closed')->values()],
            ['label' => 'Cumplimiento %', 'data' => collect($analytics['monthly_trend'])->pluck('compliance')->values(), 'yAxisID' => 'y1'],
        ],
    ];
    $statusChart = [
        'type' => 'doughnut',
        'labels' => array_keys($analytics['status_distribution']),
        'datasets' => [['label' => 'Cargas', 'data' => array_values($analytics['status_distribution'])]],
    ];
    $unitChart = [
        'type' => 'bar',
        'labels' => collect($analytics['unit_performance'])->pluck('unit')->values(),
        'datasets' => [['label' => 'Cumplimiento %', 'data' => collect($analytics['unit_performance'])->pluck('percentage')->values()]],
    ];
    $evidenceChart = [
        'type' => 'bar',
        'labels' => ['Pendiente', 'En captura', 'Enviado', 'Observado', 'Corregido', 'Validado', 'Cerrado'],
        'datasets' => [[
            'label' => 'Entregables',
            'data' => [
                $analytics['deliverable_funnel']['PENDIENTE'] ?? 0,
                $analytics['deliverable_funnel']['EN_CAPTURA'] ?? 0,
                $analytics['deliverable_funnel']['ENVIADO'] ?? 0,
                $analytics['deliverable_funnel']['OBSERVADO'] ?? 0,
                $analytics['deliverable_funnel']['CORREGIDO'] ?? 0,
                $analytics['deliverable_funnel']['VALIDADO'] ?? 0,
                $analytics['deliverable_funnel']['CERRADO'] ?? 0,
            ],
        ]],
    ];
@endphp

<section class="siget-hero mb-4">
    <span class="badge text-bg-primary mb-2">{{ $presentation['scope'] }}</span>
    <h2>{{ $presentation['title'] }}</h2>
    <p>{{ $presentation['subtitle'] }}</p>
    <div class="siget-hero-actions">
        <a class="btn btn-primary" href="{{ route($presentation['action']['route']) }}"><i class="bi {{ $presentation['action']['icon'] }} me-1"></i>{{ $presentation['action']['label'] }}</a>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.index') }}"><i class="bi bi-calendar3 me-1"></i>Calendario inteligente</a>
        <a class="btn btn-outline-secondary" href="{{ route('repository.index') }}"><i class="bi bi-folder2-open me-1"></i>Repositorio</a>
    </div>
</section>

<div class="row g-3 mb-4">
    @foreach ($cards as [$label, $value, $icon, $type])
        <div class="col-6 col-xl-2"><div class="siget-kpi"><div class="siget-kpi-icon text-bg-{{ $type }}"><i class="bi {{ $icon }}"></i></div><div><small>{{ $label }}</small><strong>{{ $value }}</strong></div></div></div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-8"><div class="card siget-card h-100"><div class="card-header"><div><h2>Tendencia mensual</h2><p>Programadas, cerradas y cumplimiento.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="monthlyTrendChart"></canvas></div></div></div></div>
    <div class="col-xl-4"><div class="card siget-card h-100"><div class="card-header"><div><h2>Distribución por estado</h2><p>Situación actual de las cargas.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="statusChart"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="card siget-card h-100"><div class="card-header"><div><h2>Cumplimiento por dirección</h2><p>Entregables validados o cerrados.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="unitChart"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="card siget-card h-100"><div class="card-header"><div><h2>Flujo de evidencias</h2><p>Entregables pendientes, capturados, enviados, observados y validados.</p></div></div><div class="card-body"><div class="siget-chart-wrap"><canvas id="evidenceLifecycleChart"></canvas></div></div></div></div>
    <div class="col-xl-6"><div class="card siget-card h-100"><div class="card-header"><div><h2>Dependencias con mayor seguimiento</h2><p>Cumplimiento y cargas vencidas.</p></div></div><div class="card-body">
        @forelse ($analytics['agency_performance'] as $a)
            <div class="siget-progress-row"><div><strong>{{ $a['agency'] }}</strong><small>{{ $a['closed'] }} de {{ $a['total'] }} cerradas</small></div><div class="progress"><div class="progress-bar" style="width:{{ $a['percentage'] }}%"></div></div><strong>{{ $a['percentage'] }}%</strong><span class="badge {{ $a['overdue'] ? 'text-bg-danger' : 'text-bg-success' }}">{{ $a['overdue'] }} vencidas</span></div>
        @empty
            <p class="text-secondary">Sin datos por dependencia.</p>
        @endforelse
    </div></div></div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-6"><div class="card siget-card"><div class="card-header"><div><h2>Próximas cargas</h2><p>Ordenadas por fecha límite.</p></div><a href="{{ route('calendar.index') }}" class="btn btn-sm btn-outline-primary">Ver calendario</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Carga</th><th>Fecha límite</th><th>Avance</th></tr></thead><tbody>
        @forelse ($analytics['upcoming'] as $load)
            <tr><td><a href="{{ route('loads.show', $load) }}"><strong>{{ $load->title }}</strong></a><small>{{ $load->agency?->name }}</small></td><td>{{ $load->effective_close_at?->format('d/m/Y H:i') }}</td><td><div class="progress"><div class="progress-bar" style="width:{{ $load->completion_percentage }}%"></div></div><small>{{ $load->completion_percentage }}%</small></td></tr>
        @empty
            <tr><td colspan="3" class="text-center py-4">Sin cargas próximas.</td></tr>
        @endforelse
    </tbody></table></div></div></div>
    <div class="col-xl-6"><div class="card siget-card"><div class="card-header"><div><h2>Actividad reciente</h2><p>Últimos expedientes actualizados.</p></div><a href="{{ route('repository.index') }}" class="btn btn-sm btn-outline-primary">Ver repositorio</a></div><div class="list-group list-group-flush">
        @forelse ($analytics['recent'] as $load)
            <a href="{{ route('loads.show', $load) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span><strong>{{ $load->title }}</strong><small>{{ $load->agency?->name }} · {{ $load->period_label }}</small></span><span class="badge siget-status">{{ $load->status instanceof \BackedEnum ? $load->status->value : $load->status }}</span></a>
        @empty
            <div class="p-4">Sin actividad.</div>
        @endforelse
    </div></div></div>
</div>

<script type="application/json" data-siget-chart="monthlyTrendChart">{!! json_encode($monthlyChart) !!}</script>
<script type="application/json" data-siget-chart="statusChart">{!! json_encode($statusChart) !!}</script>
<script type="application/json" data-siget-chart="unitChart">{!! json_encode($unitChart) !!}</script>
<script type="application/json" data-siget-chart="evidenceLifecycleChart">{!! json_encode($evidenceChart) !!}</script>
@endsection
