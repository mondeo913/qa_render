@extends('layouts.app')
@section('title','Centro de Reportes SIGET')
@section('page-title','Centro de Reportes SIGET')
@section('page-subtitle','Reportes ejecutivos, seguimiento y auditoría')
@section('content')
@php
$k = $analytics['kpis'] ?? [];
$agencyRows = collect($analytics['agency_performance'] ?? []);
$directionRows = collect($analytics['direction_performance'] ?? []);
$monthlyRows = collect($analytics['monthly_trend'] ?? []);
$statusDistribution = collect($analytics['status_distribution'] ?? []);
$riskItems = collect($analytics['risk_items'] ?? []);
$loadsByAgency = $loads->groupBy(fn($l) => $l->agency?->name ?: 'Sin dependencia');
$statuses = [
    'PROGRAMADA' => 'PROGRAMADO',
    'REPROGRAMADA' => 'REPROGRAMADO',
    'VALIDADO_Y_CERRADO' => 'VALIDADO Y CERRADO',
    'VENCIDA' => 'VENCIDO',
];

$directionLabels = [];
$directionValues = [];
foreach ($directionRows as $row) {
    $directionLabels[] = $row['unit'] ?? 'Sin unidad';
    $directionValues[] = (float) ($row['percentage'] ?? 0);
}

$trendLabels = [];
$trendValues = [];
foreach ($monthlyRows as $row) {
    $trendLabels[] = $row['period'] ?? '';
    $trendValues[] = (float) ($row['compliance'] ?? 0);
}

$statusLabels = [];
$statusValues = [];
$statusNameMap = [
    'PROGRAMADA' => 'PROGRAMADO',
    'REPROGRAMADA' => 'REPROGRAMADO',
    'VALIDADO_Y_CERRADO' => 'VALIDADO Y CERRADO',
    'VENCIDA' => 'VENCIDO',
];
foreach ($statusNameMap as $code => $label) {
    $statusLabels[] = $label;
    $statusValues[] = (int) ($statusDistribution[$code] ?? 0);
}

$agencyLabels = [];
$agencyRealized = [];
$agencyClosed = [];
foreach ($agencyRows as $row) {
    $agencyLabels[] = $row['agency'] ?? 'Sin dependencia';
    $agencyRealized[] = (float) ($row['percentage'] ?? 0);
    $agencyClosed[] = (float) ($row['closure_percentage'] ?? 0);
}

$executiveStatusLabels = ['PROGRAMADO','REPROGRAMADO','VALIDADO Y CERRADO','VENCIDO'];
$executiveStatusValues = $statusValues;

$reportStatusChart = [
    'type' => 'doughnut',
    'data' => [
        'labels' => $statusLabels,
        'datasets' => [[
            'label' => 'Cargas',
            'data' => $statusValues,
        ]],
    ],
];

$reportAgencyChart = [
    'type' => 'bar',
    'data' => [
        'labels' => $agencyLabels,
        'datasets' => [
            ['label' => 'Avance realizado %', 'data' => $agencyRealized],
            ['label' => 'Cierre validado %', 'data' => $agencyClosed],
        ],
    ],
];

$reportTrendChart = [
    'type' => 'line',
    'data' => [
        'labels' => $trendLabels,
        'datasets' => [[
            'label' => 'Cumplimiento %',
            'data' => $trendValues,
            'fill' => false,
        ]],
    ],
];

$reportDirectionChart = [
    'type' => 'bar',
    'data' => [
        'labels' => $directionLabels,
        'datasets' => [[
            'label' => 'Avance realizado %',
            'data' => $directionValues,
        ]],
    ],
];

$reportExecutiveStatusChart = [
    'type' => 'bar',
    'data' => [
        'labels' => $executiveStatusLabels,
        'datasets' => [[
            'label' => 'Cargas',
            'data' => $executiveStatusValues,
        ]],
    ],
];

$builderRows = $loads->map(function ($load) {
    $unit = $load->deliverables
        ->map(fn($d) => $d->organizationalUnit?->name)
        ->filter()
        ->unique()
        ->implode(' / ');

    return [
        'agency' => $load->agency?->name ?: 'Sin dependencia',
        'unit' => $unit ?: '—',
        'title' => $load->title,
        'status' => $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status,
        'progress' => (float) $load->completion_percentage,
        'open' => $load->effective_open_at?->format('d/m/Y H:i') ?: '—',
        'close' => $load->effective_close_at?->format('d/m/Y H:i') ?: '—',
        'evidence' => $load->deliverables->flatMap->evidences->count(),
    ];
})->values();
@endphp

<style>
.siget-report-shell{background:var(--bg);color:var(--text);min-height:100%}
.crbox,.crk{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:12px}
.crtop{padding:20px 22px;margin-bottom:15px;border-radius:14px;background:linear-gradient(135deg,var(--side),#203c63);color:#fff}
.crtop h2{margin:0;color:#fff;font-weight:800}.crtop p{margin:4px 0 0;color:#d4dfec;font-size:.76rem}.crtop .eyebrow{font-size:.62rem;text-transform:uppercase;letter-spacing:.09em;opacity:.75;margin-bottom:3px}
.toolbar{padding:14px;margin-bottom:15px}.toolbar label{font-size:.65rem;font-weight:800;margin-bottom:4px;display:block}.tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:15px}
.tab{padding:8px 11px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);font-weight:700;font-size:.7rem;cursor:pointer}.tab.on{background:var(--primary);color:#fff}.panel{display:none}.panel.on{display:block}
.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:9px;margin-bottom:15px}.crk{padding:12px;position:relative;overflow:hidden}.crk small{display:block;color:var(--muted);font-size:.58rem;text-transform:uppercase;letter-spacing:.04em}.crk strong{font-size:1.35rem;display:block;margin-top:2px}.crk em{font-style:normal;font-size:.59rem;color:var(--muted)}
.exec-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:15px}.exec-wide{margin-top:15px}.head{padding:12px 14px;background:var(--surface2);border-bottom:1px solid var(--border)}.head h3{margin:0;font-size:.9rem;color:var(--text);font-weight:800}.head p{margin:3px 0 0;font-size:.63rem;color:var(--muted)}
.chart{height:285px;padding:10px}.chart.tall{height:325px}.chart canvas{width:100%!important;height:100%!important}
.exec-insight{margin-top:15px;padding:12px 14px;border-left:4px solid var(--primary);background:var(--surface2);border-radius:0 10px 10px 0;font-size:.69rem;line-height:1.45}.exec-insight strong{font-weight:800}
.exec-table-wrap{overflow:auto}.exec-semaforo{width:100%;border-collapse:separate;border-spacing:0;font-size:.66rem;color:var(--text)}.exec-semaforo th{background:var(--side);color:#fff;padding:8px;text-align:left;font-size:.57rem;position:sticky;top:0}.exec-semaforo td{padding:8px;border-bottom:1px solid var(--border);vertical-align:middle}.exec-semaforo tr:nth-child(even) td{background:var(--surface2)}.meter{min-width:110px}.meter-bar{height:7px;border-radius:999px;background:var(--border);overflow:hidden}.meter-bar span{display:block;height:100%;border-radius:999px}.pct{font-weight:800;font-variant-numeric:tabular-nums}.traffic{display:inline-flex;align-items:center;gap:6px;padding:4px 7px;border-radius:999px;font-weight:800;font-size:.59rem;white-space:nowrap}.traffic-dot{width:8px;height:8px;border-radius:50%;background:currentColor;box-shadow:0 0 0 3px color-mix(in srgb,currentColor 15%,transparent)}
.green{color:#1fa463}.yellow{color:#d98a00}.red{color:#d64550}.gray{color:#667085}.attention{background:rgba(214,69,80,.07);color:var(--text)}
.alert-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:15px;margin-top:15px}.alert-item{padding:10px 12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center}.alert-item:last-child{border-bottom:0}.alert-main strong{font-size:.68rem;display:block}.alert-main span{font-size:.6rem;color:var(--muted)}.mini-kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;padding:12px}.mini-kpi .crk strong{font-size:1.1rem}
.band{padding:8px 11px;background:var(--side);color:#fff;font-weight:800;font-size:.7rem}.tbl{width:100%;border-collapse:collapse;font-size:.68rem;color:var(--text)}.tbl th{background:var(--side);color:#fff;padding:7px;text-align:left;font-size:.58rem}.tbl td{padding:7px;border-bottom:1px solid var(--border)}.tbl tr:nth-child(even) td{background:var(--surface2)}
.builder-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:15px;padding:15px}.builder-field{padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--surface2);margin-bottom:10px}.builder-field label{display:block;font-weight:800;font-size:.68rem;margin-bottom:5px}.builder-checks{display:grid;grid-template-columns:1fr 1fr;gap:7px}.builder-check{display:flex;gap:7px;align-items:center;padding:7px 8px;border:1px solid var(--border);border-radius:8px;background:var(--surface)}.builder-check label{margin:0;font-weight:600;font-size:.65rem}.builder-preview{min-height:260px;border:1px dashed var(--border);border-radius:10px;background:var(--surface2);padding:12px}.builder-actions{display:flex;gap:8px;flex-wrap:wrap}.builder-help{font-size:.66rem;color:var(--muted);margin-top:4px}
@media(max-width:1100px){.kpis{grid-template-columns:repeat(3,1fr)}.exec-grid,.alert-grid,.builder-grid{grid-template-columns:1fr}}@media(max-width:650px){.kpis{grid-template-columns:repeat(2,1fr)}.builder-checks{grid-template-columns:1fr}.mini-kpi{grid-template-columns:1fr}.chart,.chart.tall{height:260px}}
html[data-bs-theme=dark] .crbox,html[data-bs-theme=dark] .crk{box-shadow:0 2px 12px rgba(0,0,0,.22)}
</style>

<div class="siget-report-shell">
    <div class="crtop">
        <div class="eyebrow">SIGET · Centro de Reportes · Vista ejecutiva</div>
        <h2>Reporte Ejecutivo Institucional</h2>
        <p>Lectura para toma de decisiones: volumen, cumplimiento, capacidad de cierre, riesgos y desempeño por dependencia.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="crbox toolbar">
        <div class="row g-3 align-items-end">
            <div class="col-lg-2"><label>Dependencia</label><select name="agency_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(($filters['agency_id'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label>Dirección / Unidad</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(($filters['organizational_unit_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Estado</label><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($statuses as $c=>$l)<option value="{{ $c }}" @selected(($filters['status'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Desde</label><input type="month" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-2"><label>Hasta</label><input type="month" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-1"><button class="btn btn-primary btn-sm w-100">Aplicar</button></div>
        </div>
    </form>

    <div class="tabs">
        <button type="button" class="tab on" data-r="exec">Ejecutivo</button>
        <button type="button" class="tab" data-r="comp">Cumplimiento</button>
        <button type="button" class="tab" data-r="deps">Dependencias</button>
        <button type="button" class="tab" data-r="track">Seguimiento</button>
        <button type="button" class="tab" data-r="audit">Auditoría</button>
        @if($canBuildReports)<button type="button" class="tab" data-r="build">Constructor</button>@endif
        @if($canExport)<a class="btn btn-danger btn-sm ms-auto" href="{{ route('reports.pdf',request()->query()) }}">PDF Crystal</a><a class="btn btn-success btn-sm" href="{{ route('reports.xlsx',request()->query()) }}">Excel</a>@endif
    </div>

    <section id="exec" class="panel on">
        <div class="kpis">
            <div class="crk"><small>Total cargas</small><strong>{{ number_format((int)($k['total'] ?? 0)) }}</strong><em>universo filtrado</em></div>
            <div class="crk"><small>Realizadas</small><strong>{{ number_format((int)($k['realized'] ?? 0)) }}</strong><em>{{ number_format((float)(($k['total'] ?? 0) ? (($k['realized'] ?? 0)*100/($k['total'] ?? 1)) : 0),1) }}% del total</em></div>
            <div class="crk"><small>Validado y cerrado</small><strong>{{ number_format((int)($k['closed'] ?? 0)) }}</strong><em>cierre administrativo</em></div>
            <div class="crk"><small>Vencidas</small><strong>{{ number_format((int)($k['overdue'] ?? 0)) }}</strong><em>atención inmediata</em></div>
            <div class="crk"><small>Reprogramadas</small><strong>{{ number_format((int)($k['reprogrammed'] ?? 0)) }}</strong><em>pendientes de ejecución</em></div>
            <div class="crk"><small>Cumplimiento</small><strong>{{ number_format((float)($k['compliance'] ?? 0),1) }}%</strong><em>cerradas / total</em></div>
        </div>

        <div class="exec-grid">
            <div class="crbox">
                <div class="head"><h3>Distribución ejecutiva de cargas</h3><p>El universo se muestra por los cuatro estados ejecutivos homologados.</p></div>
                <div class="chart"><canvas id="sigetReportStatus"></canvas></div>
            </div>
            <div class="crbox">
                <div class="head"><h3>Desempeño por dependencia</h3><p>Avance realizado frente a cierre validado.</p></div>
                <div class="chart"><canvas id="sigetReportAgency"></canvas></div>
            </div>
        </div>

        <div class="exec-wide crbox">
            <div class="head"><h3>Tendencia de cumplimiento</h3><p>Lectura mensual para identificar mejora, estabilidad o deterioro del cierre.</p></div>
            <div class="chart tall"><canvas id="sigetReportTrend"></canvas></div>
            <div class="exec-insight"><strong>Lectura ejecutiva:</strong> el indicador de cumplimiento se basa en cargas <strong>VALIDADO Y CERRADO</strong> respecto al total programado del universo filtrado. La barra por dependencia separa ejecución realizada de cierre administrativo para evitar confundir entrega con cierre.</div>
        </div>

        <div class="exec-wide crbox">
            <div class="head"><h3>Semáforo institucional por dependencia</h3><p>Verde ≥ 80% · Amarillo 50–79.9% · Rojo &lt; 50%. El semáforo usa el avance realizado.</p></div>
            <div class="exec-table-wrap">
                <table class="exec-semaforo">
                    <thead><tr><th>Semáforo</th><th>Dependencia</th><th>Programadas</th><th>Realizadas</th><th>Reprogramadas</th><th>Vencidas</th><th>Avance</th><th>Cierre validado</th></tr></thead>
                    <tbody>
                    @forelse($agencyRows as $row)
                        @php
                            $pct = (float)($row['percentage'] ?? 0);
                            $closePct = (float)($row['closure_percentage'] ?? 0);
                            $trafficClass = $pct >= 80 ? 'green' : ($pct >= 50 ? 'yellow' : 'red');
                            $trafficLabel = $pct >= 80 ? 'EN CONTROL' : ($pct >= 50 ? 'ATENCIÓN' : 'CRÍTICO');
                            $barWidth = max(0, min(100, $pct));
                        @endphp
                        <tr class="{{ $trafficClass === 'red' ? 'attention' : '' }}">
                            <td><span class="traffic {{ $trafficClass }}"><span class="traffic-dot"></span>{{ $trafficLabel }}</span></td>
                            <td><strong>{{ $row['agency'] ?? 'Sin dependencia' }}</strong></td>
                            <td>{{ $row['programmed'] ?? 0 }}</td>
                            <td>{{ $row['realized'] ?? 0 }}</td>
                            <td>{{ $row['reprogrammed'] ?? 0 }}</td>
                            <td>{{ $row['overdue'] ?? 0 }}</td>
                            <td class="meter"><div class="pct">{{ number_format($pct,1) }}%</div><div class="meter-bar"><span style="width:{{ $barWidth }}%"></span></div></td>
                            <td class="pct">{{ number_format($closePct,1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4">No hay dependencias con cargas para el filtro seleccionado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert-grid">
            <div class="crbox">
                <div class="head"><h3>Focos de atención</h3><p>Priorización ejecutiva de cargas que requieren intervención.</p></div>
                <div>
                    @forelse($riskItems->take(6) as $item)
                        @php $riskStatus = $item->status instanceof \BackedEnum ? $item->status->value : (string)$item->status; @endphp
                        <div class="alert-item">
                            <div class="alert-main"><strong>{{ $item->agency?->name ?: 'Sin dependencia' }} · {{ $item->title }}</strong><span>{{ $statuses[$riskStatus] ?? $riskStatus }} · cierre {{ $item->effective_close_at?->format('d/m/Y') ?: '—' }}</span></div>
                            <span class="traffic red"><span class="traffic-dot"></span>{{ $riskStatus === 'VENCIDA' ? 'VENCIDO' : 'REVISAR' }}</span>
                        </div>
                    @empty
                        <div class="p-3 text-muted" style="font-size:.68rem">Sin focos críticos en el universo actual.</div>
                    @endforelse
                </div>
            </div>
            <div class="crbox">
                <div class="head"><h3>Indicadores de decisión</h3><p>Resumen rápido para Dirección General y Enlace Institucional.</p></div>
                <div class="mini-kpi">
                    <div class="crk"><small>Promedio de avance</small><strong>{{ number_format((float)($k['completion_average'] ?? 0),1) }}%</strong></div>
                    <div class="crk"><small>En revisión</small><strong>{{ number_format((int)($k['review_pending'] ?? 0)) }}</strong></div>
                    <div class="crk"><small>Próximas a vencer</small><strong>{{ number_format((int)($k['due_soon'] ?? 0)) }}</strong></div>
                </div>
                <div class="p-3" style="font-size:.67rem;line-height:1.5;color:var(--muted)">Utiliza primero el semáforo para ubicar dependencias con riesgo; después revisa los focos de atención y finalmente la tendencia mensual para determinar si la capacidad de cierre está mejorando.</div>
            </div>
        </div>
    </section>

    <section id="comp" class="panel"><div class="crbox"><div class="head"><h3>Cumplimiento y Desempeño</h3></div><table class="tbl"><thead><tr><th>Dependencia</th><th>Programado</th><th>Reprogramado</th><th>Validado y cerrado</th><th>Vencido</th></tr></thead><tbody>
        @foreach($agencies as $agency)
            @php $agencyLoads = $loads->filter(fn($load) => $load->agency?->id === $agency->id); @endphp
            <tr><td>{{ $agency->name }}</td><td>{{ $agencyLoads->where('status','PROGRAMADA')->count() }}</td><td>{{ $agencyLoads->where('status','REPROGRAMADA')->count() }}</td><td>{{ $agencyLoads->where('status','VALIDADO_Y_CERRADO')->count() }}</td><td>{{ $agencyLoads->where('status','VENCIDA')->count() }}</td></tr>
        @endforeach
    </tbody></table></div></section>

    <section id="deps" class="panel"><div class="crbox"><div class="head"><h3>Dependencias</h3></div><div class="chart"><canvas id="sigetReportDirection"></canvas></div><table class="tbl"><thead><tr><th>Dependencia</th><th>Cargas</th><th>Cerradas</th><th>Vencidas</th><th>Reprogramadas</th><th>Cumplimiento</th></tr></thead><tbody>
        @foreach($agencyRows as $row)<tr><td>{{ $row['agency'] ?? 'Sin dependencia' }}</td><td>{{ $row['total'] ?? 0 }}</td><td>{{ $row['closed'] ?? 0 }}</td><td>{{ $row['overdue'] ?? 0 }}</td><td>{{ $row['reprogrammed'] ?? 0 }}</td><td>{{ $row['percentage'] ?? 0 }}%</td></tr>@endforeach
    </tbody></table></div></section>

    <section id="track" class="panel"><div class="crbox"><div class="head"><h3>Seguimiento</h3></div><table class="tbl"><thead><tr><th>Dependencia</th><th>Orden</th><th>Estado</th><th>Avance</th></tr></thead><tbody>
        @foreach($loads as $load)
            @php $loadStatus = $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status; @endphp
            @if(in_array($loadStatus,['REPROGRAMADA','VENCIDA','PROGRAMADA'],true))<tr><td>{{ $load->agency?->name ?: 'Sin dependencia' }}</td><td>{{ $load->title }}</td><td>{{ $statuses[$loadStatus] ?? $loadStatus }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td></tr>@endif
        @endforeach
    </tbody></table></div></section>

    <section id="audit" class="panel"><div class="crbox"><div class="head"><h3>Auditoría</h3></div>
        @foreach($loadsByAgency as $agency=>$items)
            <div class="band">Dependencia · {{ $agency }}</div>
            <table class="tbl"><thead><tr><th>Orden</th><th>Pauta</th><th>Apertura</th><th>Entrega</th><th>Estado</th></tr></thead><tbody>
            @foreach($items as $load)
                @php $loadStatus = $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status; @endphp
                <tr><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->delivered_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $statuses[$loadStatus] ?? $loadStatus }}</td></tr>
            @endforeach
            </tbody></table>
        @endforeach
    </div></section>

    @if($canBuildReports)
    <section id="build" class="panel">
        <div class="crbox"><div class="head"><h3>Constructor de Reportes</h3><p>Construye una vista reutilizable a partir del universo filtrado.</p></div>
            <div class="builder-grid">
                <div>
                    <div class="builder-field"><label>Tipo de reporte</label><select id="builderType" class="form-select form-select-sm"><option value="executive">Ejecutivo Crystal</option><option value="compliance">Cumplimiento por dependencia</option><option value="audit">Auditoría de cargas</option><option value="followup">Seguimiento de pendientes</option></select></div>
                    <div class="builder-field"><label>Agrupar por</label><select id="builderGroup" class="form-select form-select-sm"><option value="agency">Dependencia</option><option value="unit">Dirección / Unidad</option><option value="status">Estado</option><option value="period">Pauta / periodo</option></select></div>
                    <div class="builder-field"><label>Columnas</label><div class="builder-checks">
                        <div class="builder-check"><input id="bcAgency" type="checkbox" checked><label for="bcAgency">Dependencia</label></div>
                        <div class="builder-check"><input id="bcUnit" type="checkbox" checked><label for="bcUnit">Dirección / Unidad</label></div>
                        <div class="builder-check"><input id="bcTitle" type="checkbox" checked><label for="bcTitle">Orden / carga</label></div>
                        <div class="builder-check"><input id="bcStatus" type="checkbox" checked><label for="bcStatus">Estado</label></div>
                        <div class="builder-check"><input id="bcProgress" type="checkbox" checked><label for="bcProgress">Avance</label></div>
                        <div class="builder-check"><input id="bcDates" type="checkbox"><label for="bcDates">Fechas</label></div>
                        <div class="builder-check"><input id="bcEvidence" type="checkbox"><label for="bcEvidence">Evidencias</label></div>
                    </div></div>
                    <div class="builder-actions"><button type="button" id="builderPreview" class="btn btn-primary btn-sm">Construir vista previa</button></div>
                </div>
                <div><div class="builder-preview" id="builderPreviewBox"><strong>Constructor listo.</strong><p class="builder-help">Selecciona agrupación y columnas para generar la vista previa.</p></div></div>
            </div>
        </div>
    </section>
    @endif
</div>

<script>
document.querySelectorAll('[data-r]').forEach(function(button){
    button.addEventListener('click',function(){
        document.querySelectorAll('.tab').forEach(function(item){item.classList.remove('on');});
        document.querySelectorAll('.panel').forEach(function(panel){panel.classList.remove('on');});
        button.classList.add('on');
        var target=document.getElementById(button.dataset.r);
        if(target) target.classList.add('on');
        window.dispatchEvent(new Event('resize'));
    });
});
</script>

@if($canBuildReports)
<script>
(function(){
    var box=document.getElementById('builderPreviewBox');
    var rows=@json($builderRows);
    var labels={PROGRAMADA:'PROGRAMADO',REPROGRAMADA:'REPROGRAMADO',VALIDADO_Y_CERRADO:'VALIDADO Y CERRADO',VENCIDA:'VENCIDO'};
    function esc(value){return String(value ?? '').replace(/[&<>\"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[c];});}
    function checked(id){var el=document.getElementById(id);return !!el && el.checked;}
    function render(){
        var group=document.getElementById('builderGroup').value;
        var cols=[];
        if(checked('bcAgency')) cols.push(['agency','Dependencia']);
        if(checked('bcUnit')) cols.push(['unit','Dirección / Unidad']);
        if(checked('bcTitle')) cols.push(['title','Orden / carga']);
        if(checked('bcStatus')) cols.push(['status','Estado']);
        if(checked('bcProgress')) cols.push(['progress','Avance']);
        if(checked('bcDates')){cols.push(['open','Apertura']);cols.push(['close','Cierre']);}
        if(checked('bcEvidence')) cols.push(['evidence','Evidencias']);
        var grouped={};
        rows.forEach(function(row){var key=row[group] ?? '—';if(!grouped[key]) grouped[key]=[];grouped[key].push(row);});
        var title=document.getElementById('builderType').selectedOptions[0].text;
        var groupTitle=document.getElementById('builderGroup').selectedOptions[0].text;
        var html='<div class="band">'+esc(title)+' · '+esc(groupTitle)+' · '+rows.length+' registros</div>';
        Object.keys(grouped).sort().forEach(function(key){
            html+='<div style="margin-top:10px;font-weight:800">'+esc(key)+'</div><div style="overflow:auto"><table class="tbl"><thead><tr>';
            cols.forEach(function(col){html+='<th>'+esc(col[1])+'</th>';});
            html+='</tr></thead><tbody>';
            grouped[key].forEach(function(row){html+='<tr>';cols.forEach(function(col){var value=row[col[0]];if(col[0]==='status') value=labels[value] ?? value;if(col[0]==='progress') value=Number(value).toFixed(0)+'%';html+='<td>'+esc(value)+'</td>';});html+='</tr>';});
            html+='</tbody></table></div>';
        });
        box.innerHTML=html;
    }
    document.getElementById('builderPreview').addEventListener('click',render);
})();
</script>
@endif

<script type="application/json" data-siget-chart="sigetReportStatus">{!! json_encode($reportStatusChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportAgency">{!! json_encode($reportAgencyChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportTrend">{!! json_encode($reportTrendChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportDirection">{!! json_encode($reportDirectionChart) !!}</script>
<script type="application/json" data-siget-chart="sigetReportExecutiveStatus">{!! json_encode($reportExecutiveStatusChart) !!}</script>
@endsection