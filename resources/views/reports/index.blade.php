@extends('layouts.app')
@section('title', 'Centro de Reportes SIGET')
@section('page-title', 'Centro de Reportes SIGET')
@section('page-subtitle', 'Reportes ejecutivos, seguimiento y auditoría')
@section('content')
@php
    $k = $analytics['kpis'] ?? [];
    $statusDistribution = collect($analytics['status_distribution'] ?? []);
    $monthlyTrend = collect($analytics['monthly_trend'] ?? []);
    $unitsPerformance = collect($analytics['unit_performance'] ?? []);
    $agenciesPerformance = collect($analytics['agency_performance'] ?? []);
    $total = (int) ($k['total'] ?? 0);
    $active = (int) ($k['active'] ?? 0);
    $closed = (int) ($k['closed'] ?? 0);
    $overdue = (int) ($k['overdue'] ?? 0);
    $reprogrammed = (int) ($k['reprogrammed'] ?? 0);
    $compliance = (float) ($k['compliance'] ?? 0);
    $statuses = [
        'PROGRAMADA' => 'PROGRAMADO',
        'REPROGRAMADA' => 'REPROGRAMADO',
        'VALIDADO_Y_CERRADO' => 'VALIDADO Y CERRADO',
        'VENCIDA' => 'VENCIDO',
    ];
    $groupedLoads = $loads->groupBy(function ($load) {
        return $load->agency?->name ?: 'Sin dependencia';
    });
    $loadStatus = function ($load) {
        return $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status;
    };
    $statusMeta = function ($code) {
        if ($code === 'VALIDADO_Y_CERRADO') {
            return ['class' => 'ok', 'label' => 'VALIDADO Y CERRADO'];
        }
        if ($code === 'REPROGRAMADA') {
            return ['class' => 'warn', 'label' => 'REPROGRAMADO'];
        }
        if ($code === 'VENCIDA') {
            return ['class' => 'danger', 'label' => 'VENCIDO'];
        }
        return ['class' => 'info', 'label' => 'PROGRAMADO'];
    };

    $reportStatusChart = [
        'type' => 'doughnut',
        'data' => [
            'labels' => array_values($statuses),
            'datasets' => [[
                'label' => 'Cargas',
                'data' => array_map(function ($code) use ($statusDistribution) {
                    return (int) ($statusDistribution[$code] ?? 0);
                }, array_keys($statuses)),
            ]],
        ],
    ];
    $reportAgencyChart = [
        'type' => 'bar',
        'data' => [
            'labels' => $agenciesPerformance->map(function ($row) {
                return $row['agency'] ?? 'Sin dependencia';
            })->values()->all(),
            'datasets' => [[
                'label' => 'Cumplimiento %',
                'data' => $agenciesPerformance->map(function ($row) {
                    return (float) ($row['percentage'] ?? 0);
                })->values()->all(),
            ]],
        ],
    ];
    $reportMonthlyChart = [
        'type' => 'line',
        'data' => [
            'labels' => $monthlyTrend->map(function ($row) {
                return $row['period'] ?? '';
            })->values()->all(),
            'datasets' => [
                [
                    'label' => 'Cumplimiento %',
                    'data' => $monthlyTrend->map(function ($row) {
                        return (float) ($row['compliance'] ?? 0);
                    })->values()->all(),
                ],
                [
                    'label' => 'Cierres',
                    'data' => $monthlyTrend->map(function ($row) {
                        return (int) ($row['closed'] ?? 0);
                    })->values()->all(),
                ],
            ],
        ],
    ];
    $reportUnitsChart = [
        'type' => 'bar',
        'data' => [
            'labels' => $unitsPerformance->map(function ($row) {
                return $row['unit'] ?? 'Sin unidad';
            })->values()->all(),
            'datasets' => [[
                'label' => 'Cumplimiento %',
                'data' => $unitsPerformance->map(function ($row) {
                    return (float) ($row['percentage'] ?? 0);
                })->values()->all(),
            ]],
        ],
    ];
@endphp

<style>
:root{--cr-navy:#10213b;--cr-line:#d9e0ea;--cr-text:#1b2638;--cr-muted:#657287;--cr-ok:#20a865;--cr-warn:#db9b17;--cr-danger:#d94a4a;--cr-info:#2e79b9}
.siget-report-shell{color:var(--cr-text)}
.siget-report-top{background:linear-gradient(135deg,var(--cr-navy),#203c63);color:#fff;border-radius:14px;padding:20px 22px;margin-bottom:16px}
.siget-report-top h2{margin:.15rem 0 .3rem;font-weight:800}.siget-report-top p{margin:0;color:#d4dfec;font-size:.75rem}
.cr-toolbar,.cr-paper{background:#fff;border:1px solid var(--cr-line);border-radius:12px}.cr-toolbar{padding:14px;margin-bottom:16px}.cr-toolbar .label{font-size:.64rem;text-transform:uppercase;color:var(--cr-muted);font-weight:700;margin-bottom:5px}
.cr-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}.cr-tab{border:1px solid #cdd6e2;background:#fff;color:var(--cr-navy);border-radius:9px;padding:9px 12px;font-size:.7rem;font-weight:700;cursor:pointer}.cr-tab.active{background:var(--cr-navy);color:#fff}
.cr-panel{display:none}.cr-panel.active{display:block}.cr-paper{overflow:hidden;margin-bottom:16px}.cr-paper-head{padding:14px 16px;border-bottom:1px solid var(--cr-line);background:#f8fafc}.cr-paper-head h3{font-size:.96rem;margin:0;color:var(--cr-navy);font-weight:800}.cr-paper-head p{font-size:.68rem;margin:4px 0 0;color:var(--cr-muted)}
.cr-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:16px}.cr-kpi{background:#fff;border:1px solid var(--cr-line);border-top:4px solid var(--cr-info);border-radius:10px;padding:12px}.cr-kpi.ok{border-top-color:var(--cr-ok)}.cr-kpi.warn{border-top-color:var(--cr-warn)}.cr-kpi.danger{border-top-color:var(--cr-danger)}.cr-kpi small{display:block;color:var(--cr-muted);font-size:.63rem;text-transform:uppercase}.cr-kpi strong{display:block;color:var(--cr-navy);font-size:1.35rem;margin-top:4px}
.cr-grid-2{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.cr-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.cr-chart{padding:14px;min-height:280px}.cr-chart canvas{width:100%!important;height:280px!important}
.cr-table{width:100%;border-collapse:collapse;font-size:.7rem}.cr-table th{background:var(--cr-navy);color:#fff;padding:8px;text-align:left;font-size:.61rem;text-transform:uppercase}.cr-table td{border-bottom:1px solid #e6ebf1;padding:8px;vertical-align:middle}.cr-table tr:nth-child(even) td{background:#fbfcfd}
.status-card{padding:10px;border:1px solid var(--cr-line);border-left:4px solid var(--cr-info);margin:10px;border-radius:8px}.status-card.ok{border-left-color:var(--cr-ok)}.status-card.warn{border-left-color:var(--cr-warn)}.status-card.danger{border-left-color:var(--cr-danger)}
.cr-band{padding:10px 14px;background:var(--cr-navy);color:#fff;font-size:.72rem;font-weight:800}.cr-unit-head{padding:8px 14px;background:#f7f9fb;border-top:1px solid var(--cr-line);border-bottom:1px solid var(--cr-line);font-size:.67rem;font-weight:800}.cr-detail{overflow:auto}.semaforo{font-size:.6rem;font-weight:800}.ok{color:var(--cr-ok)}.warn{color:#9b6a00}.danger{color:var(--cr-danger)}.info{color:var(--cr-info)}
@media(max-width:1100px){.cr-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.cr-grid-2{grid-template-columns:1fr}.cr-grid-3{grid-template-columns:1fr 1fr}}@media(max-width:700px){.cr-kpis{grid-template-columns:1fr 1fr}.cr-grid-3{grid-template-columns:1fr}.cr-tabs{overflow:auto;flex-wrap:nowrap}.cr-tab{white-space:nowrap}}
</style>

<div class="siget-report-shell">
    <div class="siget-report-top">
        <div style="text-transform:uppercase;letter-spacing:.08em;font-size:.64rem;opacity:.72;font-weight:700">SIGET · Centro de Reportes · Formato Crystal</div>
        <h2>Reportes Ejecutivos y de Auditoría</h2>
        <p>Lectura jerárquica por Dependencia → Dirección / Unidad → Orden / Pauta.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="cr-toolbar">
        <div class="row g-3 align-items-end">
            <div class="col-xl-2 col-md-4"><div class="label">Dependencia</div><select name="agency_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @if((string)($filters['agency_id'] ?? '') === (string)$agency->id) selected @endif>{{ $agency->name }}</option>@endforeach</select></div>
            <div class="col-xl-3 col-md-4"><div class="label">Dirección / Unidad</div><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @if((string)($filters['organizational_unit_id'] ?? '') === (string)$unit->id) selected @endif>{{ $unit->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Estado</div><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($statuses as $code => $label)<option value="{{ $code }}" @if(($filters['status'] ?? '') === $code) selected @endif>{{ $label }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Desde</div><input type="month" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-2 col-md-4"><div class="label">Hasta</div><input type="month" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-1 col-md-4"><button class="btn btn-primary btn-sm w-100">Aplicar</button></div>
        </div>
    </form>

    <div class="cr-tabs">
        <button type="button" class="cr-tab active" data-cr-tab="executive">1 · Ejecutivo</button>
        <button type="button" class="cr-tab" data-cr-tab="compliance">2 · Cumplimiento</button>
        <button type="button" class="cr-tab" data-cr-tab="dependencies">3 · Dependencias</button>
        <button type="button" class="cr-tab" data-cr-tab="tracking">4 · Seguimiento</button>
        <button type="button" class="cr-tab" data-cr-tab="audit">5 · Auditoría</button>
        @if($canBuildReports)<button type="button" class="cr-tab" data-cr-tab="builder">6 · Constructor</button>@endif
        @if($canExport)<a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-danger btn-sm ms-auto">PDF Crystal</a><a href="{{ route('reports.xlsx', request()->query()) }}" class="btn btn-success btn-sm">Excel</a>@endif
    </div>

    <section class="cr-panel active" data-cr-panel="executive">
        <div class="cr-kpis">
            <div class="cr-kpi"><small>Total de cargas</small><strong>{{ number_format($total) }}</strong></div>
            <div class="cr-kpi"><small>Activas</small><strong>{{ number_format($active) }}</strong></div>
            <div class="cr-kpi ok"><small>Validadas y cerradas</small><strong>{{ number_format($closed) }}</strong></div>
            <div class="cr-kpi danger"><small>Vencidas / faltantes</small><strong>{{ number_format($overdue) }}</strong></div>
            <div class="cr-kpi warn"><small>Reprogramadas</small><strong>{{ number_format($reprogrammed) }}</strong></div>
            <div class="cr-kpi"><small>Cumplimiento</small><strong>{{ number_format($compliance,1) }}%</strong></div>
        </div>
        <div class="cr-grid-2">
            <div class="cr-paper"><div class="cr-paper-head"><h3>Distribución por estado</h3><p>Los cuatro estados oficiales de lectura ejecutiva.</p></div><div class="cr-grid-2" style="grid-template-columns:1fr 1fr;gap:0"><div class="cr-chart"><canvas id="sigetReportStatus"></canvas></div><div>@foreach($statuses as $code => $label) @php($qty = (int)($statusDistribution[$code] ?? 0)) @endphp @php($m=$statusMeta($code)) @endphp<div class="status-card {{ $m['class'] }}"><strong>{{ $label }}</strong><span style="float:right;font-size:1.1rem">{{ $qty }}</span></div>@endforeach</div></div></div>
            <div class="cr-paper"><div class="cr-paper-head"><h3>Cumplimiento por dependencia</h3><p>Comparación institucional.</p></div><div class="cr-chart"><canvas id="sigetReportAgency"></canvas></div></div>
        </div>
        <div class="cr-paper"><div class="cr-paper-head"><h3>Resumen por dependencia</h3><p>Subtotal institucional y semáforo.</p></div>
            @if($groupedLoads->isEmpty())<div class="p-4 text-muted">No existen datos para el universo seleccionado.</div>@endif
            @foreach($groupedLoads as $agency => $agencyLoads)
                <div class="cr-band">{{ $agency }} · {{ $agencyLoads->count() }} cargas</div>
                <div class="cr-detail"><table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Estado</th><th>Avance</th></tr></thead><tbody>
                @foreach($agencyLoads as $load)
                    @php($m = $statusMeta($loadStatus($load)))
                    <tr><td><strong>{{ $load->title }}</strong></td><td>{{ $load->period_label ?: '—' }}</td><td><span class="semaforo {{ $m['class'] }}">{{ $m['label'] }}</span></td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td></tr>
                @endforeach
                </tbody></table></div>
            @endforeach
        </div>
    </section>

    <section class="cr-panel" data-cr-panel="compliance">
        <div class="cr-paper"><div class="cr-paper-head"><h3>Cumplimiento y Desempeño</h3><p>Programado → Reprogramado → Validado y cerrado → Vencido.</p></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dependencia</th><th>Programado</th><th>Reprogramado</th><th>Validado y cerrado</th><th>Vencido</th><th>Cumplimiento</th></tr></thead><tbody>
        @foreach($agencies as $agency)
            @php($agencyLoads=$loads->filter(function($load) use ($agency){ return $load->agency?->id === $agency->id; }))
            @php($count=$agencyLoads->count())
            @php($ac=$agencyLoads->where('status','VALIDADO_Y_CERRADO')->count())
            @php($ap=$agencyLoads->where('status','PROGRAMADA')->count())
            @php($ar=$agencyLoads->where('status','REPROGRAMADA')->count())
            @php($av=$agencyLoads->where('status','VENCIDA')->count())
            @php($apct=$count ? round(100*$ac/$count,1) : 0)
            <tr><td><strong>{{ $agency->name }}</strong></td><td>{{ $ap }}</td><td>{{ $ar }}</td><td>{{ $ac }}</td><td>{{ $av }}</td><td>{{ $apct }}%</td></tr>
        @endforeach
        @if($agencies->isEmpty())<tr><td colspan="6" class="text-center p-4 text-muted">Sin dependencias para los filtros actuales.</td></tr>@endif
        </tbody></table></div></div>
        <div class="cr-paper"><div class="cr-paper-head"><h3>Evolución mensual del cumplimiento</h3><p>Comportamiento del universo contratado por periodo.</p></div><div class="cr-chart"><canvas id="sigetReportMonthly"></canvas></div></div>
    </section>

    <section class="cr-panel" data-cr-panel="dependencies">
        <div class="cr-paper"><div class="cr-paper-head"><h3>Dependencias</h3><p>Resumen por dependencia con lectura de riesgo.</p></div><table class="cr-table"><thead><tr><th>Dependencia</th><th>Cargas</th><th>Cerradas</th><th>Vencidas</th><th>Reprogramadas</th><th>Cumplimiento</th></tr></thead><tbody>
        @foreach($agenciesPerformance as $row)
            <tr><td><strong>{{ $row['agency'] ?? 'Sin dependencia' }}</strong></td><td>{{ $row['total'] ?? 0 }}</td><td>{{ $row['closed'] ?? 0 }}</td><td>{{ $row['overdue'] ?? 0 }}</td><td>{{ $row['reprogrammed'] ?? 0 }}</td><td>{{ $row['percentage'] ?? 0 }}%</td></tr>
        @endforeach
        @if($agenciesPerformance->isEmpty())<tr><td colspan="6" class="text-center p-4 text-muted">Sin dependencias para los filtros actuales.</td></tr>@endif
        </tbody></table></div>
        <div class="cr-paper"><div class="cr-paper-head"><h3>Direcciones / Unidades</h3><p>Desempeño dentro del universo seleccionado.</p></div><div class="cr-chart"><canvas id="sigetReportUnits"></canvas></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dirección / Unidad</th><th>Entregables</th><th>Validados</th><th>Cumplimiento</th></tr></thead><tbody>
        @foreach($unitsPerformance as $row)<tr><td>{{ $row['unit'] ?? 'Sin unidad' }}</td><td>{{ $row['total'] ?? 0 }}</td><td>{{ $row['validated'] ?? 0 }}</td><td>{{ $row['percentage'] ?? 0 }}%</td></tr>@endforeach
        @if($unitsPerformance->isEmpty())<tr><td colspan="4" class="text-center p-4 text-muted">Sin información.</td></tr>@endif
        </tbody></table></div></div>
    </section>

    <section class="cr-panel" data-cr-panel="tracking">
        <div class="cr-paper"><div class="cr-paper-head"><h3>Seguimiento de cargas</h3><p>Focos de atención para seguimiento ejecutivo.</p></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dependencia</th><th>Orden / referencia</th><th>Pauta</th><th>Estado</th><th>Avance</th></tr></thead><tbody>
        @foreach($loads as $load)
            @php($statusCode=$loadStatus($load))
            @if(in_array($statusCode,['REPROGRAMADA','VENCIDA','PROGRAMADA'],true))
                @php($m=$statusMeta($statusCode))
                <tr><td>{{ $load->agency?->name ?: 'Sin dependencia' }}</td><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $m['label'] }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td></tr>
            @endif
        @endforeach
        @if($loads->isEmpty())<tr><td colspan="5" class="text-center p-4 text-muted">Sin cargas.</td></tr>@endif
        </tbody></table></div></div>
        <div class="cr-paper"><div class="p-3 text-muted">La lectura ejecutiva prioriza VENCIDO y REPROGRAMADO como focos de atención; las cargas PROGRAMADAS permanecen en seguimiento hasta completar su ciclo.</div></div>
    </section>

    <section class="cr-panel" data-cr-panel="audit">
        <div class="cr-paper"><div class="cr-paper-head"><h3>Auditoría / Detalle</h3><p>Jerarquía documental: Dependencia → Orden / Pauta.</p></div>
        @if($groupedLoads->isEmpty())<div class="p-4 text-muted">No hay registros para los filtros seleccionados.</div>@endif
        @foreach($groupedLoads as $agency => $agencyLoads)
            <div class="cr-band">Dependencia · {{ $agency }}</div>
            <div class="cr-detail"><table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Fecha apertura</th><th>Fecha entrega</th><th>Estado</th></tr></thead><tbody>
            @foreach($agencyLoads as $load)
                @php($m=$statusMeta($loadStatus($load)))
                <tr><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->delivered_at?->format('d/m/Y H:i') ?: '—' }}</td><td><span class="semaforo {{ $m['class'] }}">{{ $m['label'] }}</span></td></tr>
            @endforeach
            </tbody></table></div>
        @endforeach
        </div>
    </section>

    @if($canBuildReports)
    <section class="cr-panel" data-cr-panel="builder"><div class="cr-paper"><div class="cr-paper-head"><h3>Constructor de Reportes</h3><p>Generación formal del reporte ejecutivo con filtros actuales.</p></div><div class="p-3"><div class="cr-grid-3"><div class="status-card info"><strong>1 · Dependencia</strong><br><small>Agrupar y subtotalizar por dependencia.</small></div><div class="status-card info"><strong>2 · Dirección / Unidad</strong><br><small>Separar cada dirección dentro de su dependencia.</small></div><div class="status-card info"><strong>3 · Orden / Pauta</strong><br><small>Detalle documental de cada carga.</small></div></div></div></div></section>
    @endif
</div>

<script>
(function(){
    var tabs=document.querySelectorAll('[data-cr-tab]');
    var panels=document.querySelectorAll('[data-cr-panel]');
    tabs.forEach(function(tab){
        tab.addEventListener('click',function(){
            var key=tab.getAttribute('data-cr-tab');
            tabs.forEach(function(item){item.classList.toggle('active',item===tab);});
            panels.forEach(function(panel){panel.classList.toggle('active',panel.getAttribute('data-cr-panel')===key);});
            window.dispatchEvent(new Event('resize'));
        });
    });
})();
</script>

<script type="application/json" data-siget-chart="sigetReportStatus">{!! json_encode($reportStatusChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportAgency">{!! json_encode($reportAgencyChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportMonthly">{!! json_encode($reportMonthlyChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportUnits">{!! json_encode($reportUnitsChart) !!}</script>
@endsection
