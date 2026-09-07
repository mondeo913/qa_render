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
    $statusValues = collect($statuses)->mapWithKeys(fn ($label, $code) => [$label => (int) ($statusDistribution[$code] ?? 0)]);

    $statusMeta = function (string $status): array {
        return match ($status) {
            'VALIDADO_Y_CERRADO' => ['class' => 'ok', 'icon' => '●', 'label' => 'VALIDADO Y CERRADO', 'note' => 'Cumplimiento confirmado'],
            'REPROGRAMADA' => ['class' => 'warn', 'icon' => '●', 'label' => 'REPROGRAMADO', 'note' => 'Requiere seguimiento'],
            'VENCIDA' => ['class' => 'danger', 'icon' => '●', 'label' => 'VENCIDO', 'note' => 'Incumplimiento'],
            default => ['class' => 'info', 'icon' => '●', 'label' => 'PROGRAMADO', 'note' => 'En seguimiento'],
        };
    };

    $loadStatus = fn ($load) => $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status;
    $agencyName = fn ($load) => $load->agency?->name ?: 'Sin dependencia';
    $unitNames = fn ($load) => $load->deliverables
        ->map(fn ($deliverable) => $deliverable->organizationalUnit?->name)
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $groupedLoads = $loads->groupBy(fn ($load) => $agencyName($load));
    $riskLoads = $loads->filter(fn ($load) => in_array($loadStatus($load), ['VENCIDA', 'REPROGRAMADA'], true));

    $statusLabels = array_values($statuses);
    $statusChartData = array_values(array_map(fn ($code) => (int) ($statusDistribution[$code] ?? 0), array_keys($statuses)));
    $agencyLabels = $agenciesPerformance->map(fn ($row) => $row['agency'] ?? 'Sin dependencia')->values()->all();
    $agencyPct = $agenciesPerformance->map(fn ($row) => (float) ($row['percentage'] ?? 0))->values()->all();
    $agencyTotal = $agenciesPerformance->map(fn ($row) => (int) ($row['total'] ?? 0))->values()->all();
    $unitLabels = $unitsPerformance->map(fn ($row) => $row['unit'] ?? 'Sin unidad')->values()->all();
    $unitPct = $unitsPerformance->map(fn ($row) => (float) ($row['percentage'] ?? 0))->values()->all();
    $trendLabels = $monthlyTrend->map(fn ($row) => $row['period'] ?? '')->values()->all();
    $trendCompliance = $monthlyTrend->map(fn ($row) => (float) ($row['compliance'] ?? 0))->values()->all();
    $trendClosed = $monthlyTrend->map(fn ($row) => (int) ($row['closed'] ?? 0))->values()->all();
    $trendTotal = $monthlyTrend->map(fn ($row) => (int) ($row['total'] ?? 0))->values()->all();
@endphp

<style>
:root{--cr-navy:#10213b;--cr-navy-2:#172c4b;--cr-line:#d9e0ea;--cr-soft:#f5f7fa;--cr-text:#1b2638;--cr-muted:#657287;--cr-ok:#20a865;--cr-warn:#db9b17;--cr-danger:#d94a4a;--cr-info:#2e79b9}
.siget-report-shell{color:var(--cr-text)}
.siget-report-top{background:linear-gradient(135deg,var(--cr-navy),#203c63);color:#fff;border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:0 8px 22px rgba(16,33,59,.13)}
.siget-report-top .eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:.64rem;opacity:.72;font-weight:700}.siget-report-top h2{margin:.15rem 0 .3rem;font-weight:800}.siget-report-top p{margin:0;color:#d4dfec;font-size:.75rem}
.cr-toolbar{background:#fff;border:1px solid var(--cr-line);border-radius:12px;padding:14px;margin-bottom:16px}.cr-toolbar .label{font-size:.64rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cr-muted);font-weight:700;margin-bottom:5px}
.cr-toolbar .form-select,.cr-toolbar .form-control{border-color:#cfd7e2}.cr-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}.cr-tab{border:1px solid #cdd6e2;background:#fff;color:var(--cr-navy);border-radius:9px;padding:9px 12px;font-size:.7rem;font-weight:700;cursor:pointer}.cr-tab.active{background:var(--cr-navy);color:#fff;border-color:var(--cr-navy)}
.cr-panel{display:none}.cr-panel.active{display:block}
.cr-paper{background:#fff;border:1px solid var(--cr-line);border-radius:12px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 8px rgba(16,33,59,.04)}
.cr-paper-head{background:var(--cr-navy);color:#fff;padding:14px 17px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.cr-paper-head h3{font-size:.95rem;margin:0;font-weight:800}.cr-paper-head p{margin:3px 0 0;color:#d6dfeb;font-size:.68rem}.cr-band{padding:9px 13px;background:#eef2f7;border-top:1px solid var(--cr-line);border-bottom:1px solid var(--cr-line);font-size:.68rem;font-weight:800;color:var(--cr-navy);text-transform:uppercase;letter-spacing:.04em}
.cr-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:9px;margin-bottom:16px}.cr-kpi{background:#fff;border:1px solid var(--cr-line);border-left:4px solid var(--cr-navy);border-radius:10px;padding:12px 13px}.cr-kpi small{display:block;color:var(--cr-muted);font-size:.62rem;text-transform:uppercase;letter-spacing:.04em}.cr-kpi strong{display:block;font-size:1.45rem;line-height:1.1;margin-top:4px;color:var(--cr-navy)}.cr-kpi.ok{border-left-color:var(--cr-ok)}.cr-kpi.warn{border-left-color:var(--cr-warn)}.cr-kpi.danger{border-left-color:var(--cr-danger)}
.cr-grid-2{display:grid;grid-template-columns:1.05fr 1fr;gap:14px}.cr-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}.cr-chart{height:260px;padding:13px}.cr-chart.tall{height:300px}
.cr-table{width:100%;border-collapse:collapse;font-size:.69rem}.cr-table th{background:#15263f;color:#fff;padding:8px 10px;text-align:left;text-transform:uppercase;font-size:.59rem;letter-spacing:.03em}.cr-table td{padding:8px 10px;border-bottom:1px solid #e3e7ed;vertical-align:middle}.cr-table tr:last-child td{border-bottom:0}.cr-table .subtotal td{background:#f1f4f8;font-weight:800;border-top:1px solid #cfd7e2}
.semaforo{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 8px;font-size:.6rem;font-weight:800;border:1px solid transparent}.semaforo .dot{font-size:.56rem}.semaforo.ok{color:#0f7b49;background:#e8f7ef;border-color:#bce8d0}.semaforo.warn{color:#996a00;background:#fff5d9;border-color:#f0d993}.semaforo.danger{color:#a72b2b;background:#ffeded;border-color:#efc2c2}.semaforo.info{color:#1f649c;background:#eaf4fc;border-color:#c2def3}
.status-card{border:1px solid var(--cr-line);border-radius:10px;padding:11px;background:#fff}.status-card .status-top{display:flex;justify-content:space-between;gap:8px}.status-card strong{font-size:.74rem}.status-card .n{font-size:1.25rem;font-weight:800;color:var(--cr-navy)}.status-card small{display:block;color:var(--cr-muted);font-size:.61rem;margin-top:2px}.status-legend{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;padding:13px}.status-card.ok{border-left:4px solid var(--cr-ok)}.status-card.warn{border-left:4px solid var(--cr-warn)}.status-card.danger{border-left:4px solid var(--cr-danger)}.status-card.info{border-left:4px solid var(--cr-info)}
.cr-agency{border:1px solid var(--cr-line);border-radius:11px;margin-bottom:12px;overflow:hidden}.cr-agency-head{background:#eaf0f6;padding:10px 12px;display:flex;justify-content:space-between;align-items:center}.cr-agency-head strong{color:var(--cr-navy);font-size:.75rem}.cr-agency-head span{font-size:.62rem;color:var(--cr-muted)}.cr-unit-head{background:#f7f9fb;padding:8px 12px;border-top:1px solid var(--cr-line);border-bottom:1px solid var(--cr-line);font-size:.66rem;font-weight:800;color:#33435b}.cr-detail{padding:9px 12px}
.cr-risk-list{display:grid;gap:8px;padding:12px}.risk-item{display:grid;grid-template-columns:1.5fr .7fr .9fr 1fr;gap:8px;align-items:center;border:1px solid #e0e5ec;border-radius:9px;padding:9px;font-size:.66rem}.risk-item strong{font-size:.8rem}.cr-actions{display:flex;justify-content:flex-end;gap:7px;padding:12px;border-top:1px solid var(--cr-line)}.cr-note{padding:10px 12px;background:#fff8e7;border-left:3px solid var(--cr-warn);margin:12px;font-size:.66rem;color:#6f5521}
@media(max-width:1100px){.cr-kpis{grid-template-columns:repeat(3,1fr)}.cr-grid-2,.cr-grid-3{grid-template-columns:1fr}.risk-item{grid-template-columns:1fr 1fr}}
@media(max-width:650px){.cr-kpis{grid-template-columns:repeat(2,1fr)}.status-legend{grid-template-columns:1fr}}
</style>

<div class="siget-report-shell">
    <div class="siget-report-top">
        <div class="eyebrow">SIGET · Centro de Reportes</div>
        <h2>Información para decisión y seguimiento</h2>
        <p>Formato Crystal institucional · agrupación por Dependencia → Dirección / Unidad → Orden / referencia → Pauta / periodo.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="cr-toolbar">
        <div class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4"><div class="label">Dependencia</div><select name="agency_id" class="form-select form-select-sm"><option value="">Todas las dependencias</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency_id'] ?? '') === (string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
            <div class="col-xl-3 col-md-4"><div class="label">Dirección / unidad</div><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas las direcciones</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)($filters['organizational_unit_id'] ?? '') === (string)$unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Estado</div><select name="status" class="form-select form-select-sm"><option value="">Todos los estados</option>@foreach($statuses as $code=>$label)<option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Desde</div><input type="month" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-2 col-md-4"><div class="label">Hasta</div><input type="month" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-1 col-md-4"><button class="btn btn-primary btn-sm w-100">Aplicar</button></div>
        </div>
    </form>

    <div class="cr-tabs">
        <button class="cr-tab active" type="button" data-cr-tab="executive">1 · Ejecutivo</button>
        <button class="cr-tab" type="button" data-cr-tab="compliance">2 · Cumplimiento</button>
        <button class="cr-tab" type="button" data-cr-tab="dependencies">3 · Dependencias y Direcciones</button>
        <button class="cr-tab" type="button" data-cr-tab="tracking">4 · Seguimiento</button>
        <button class="cr-tab" type="button" data-cr-tab="audit">5 · Auditoría</button>
        @if($canBuildReports)<button class="cr-tab" type="button" data-cr-tab="builder">6 · Constructor</button>@endif
        @if($canExport)
            <a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm ms-auto">PDF</a>
            <a href="{{ route('reports.xlsx', request()->query()) }}" class="btn btn-outline-success btn-sm">Excel</a>
            <a href="{{ route('reports.csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">CSV</a>
        @endif
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
            <div class="cr-paper"><div class="cr-paper-head"><div><h3>Distribución por estado</h3><p>Lectura ejecutiva de los cuatro estados oficiales.</p></div></div><div class="cr-grid-2" style="grid-template-columns:1fr 1fr;gap:0"><div class="cr-chart"><canvas id="sigetReportStatus"></canvas></div><div class="status-legend">@foreach($statuses as $code=>$label) @php($m=$statusMeta($code))<div class="status-card {{ $m['class'] }}"><div class="status-top"><strong>{{ $label }}</strong><span class="n">{{ (int)($statusDistribution[$code] ?? 0) }}</span></div><small>{{ $m['note'] }}</small></div>@endforeach</div></div></div>
            <div class="cr-paper"><div class="cr-paper-head"><div><h3>Cumplimiento por dependencia</h3><p>Comparación de desempeño y presión de riesgo.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportAgency"></canvas></div></div>
        </div>

        <div class="cr-paper">
            <div class="cr-paper-head"><div><h3>Resumen por dependencia</h3><p>Cada dependencia inicia una sección formal con subtotal y semáforo.</p></div><span class="badge text-bg-light">{{ $groupedLoads->count() }} dependencias</span></div>
            @forelse($groupedLoads as $agency=>$agencyLoads)
                @php $agencyKpi=$agenciesPerformance->first(fn($r)=>($r['agency']??'')===$agency); $pct=(float)($agencyKpi['percentage']??0); $late=(int)($agencyKpi['overdue']??0); @endphp
                <div class="cr-agency">
                    <div class="cr-agency-head"><strong>{{ $agency }}</strong><span>{{ $agencyLoads->count() }} cargas · {{ number_format($pct,1) }}% cumplimiento</span></div>
                    @php($byUnit=$agencyLoads->flatMap(fn($load)=>$unitNames($load)->map(fn($unit)=>['unit'=>$unit,'load'=>$load]))->groupBy('unit'))
                    @foreach($byUnit as $unit=>$entries)
                        <div class="cr-unit-head">Dirección / Unidad: {{ $unit }} · {{ $entries->count() }} cargas</div>
                        <div class="cr-detail"><table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Apertura</th><th>Estado</th><th>Avance</th><th>Semáforo</th></tr></thead><tbody>
                        @foreach($entries as $entry) @php($load=$entry['load']) @php($statusCode=$loadStatus($load)) @php($m=$statusMeta($statusCode))<tr><td><strong>{{ $load->title }}</strong></td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y') ?: '—' }}</td><td>{{ $m['label'] }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">{{ $m['icon'] }}</span>{{ $m['class']==='ok'?'EN CUMPLIMIENTO':($m['class']==='warn'?'ATENCIÓN':($m['class']==='danger'?'INCUMPLIMIENTO':'SEGUIMIENTO')) }}</span></td></tr>@endforeach
                        </tbody></table></div>
                    @endforeach
                    <div class="cr-band">Subtotal {{ $agency }} · {{ $agencyLoads->count() }} cargas · {{ $agencyLoads->where('status','VALIDADO_Y_CERRADO')->count() }} cerradas · {{ $agencyLoads->where('status','REPROGRAMADA')->count() }} reprogramadas · {{ $agencyLoads->where('status','VENCIDA')->count() }} vencidas</div>
                </div>
            @empty
                <div class="p-4 text-muted">No existen datos para el universo seleccionado.</div>
            @endforelse
        </div>
    </section>

    <section class="cr-panel" data-cr-panel="compliance">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Cumplimiento y Desempeño</h3><p>Programado → Reprogramado → Validado y cerrado → Vencido.</p></div></div>
            <table class="cr-table"><thead><tr><th>Dependencia</th><th>Programado</th><th>Reprogramado</th><th>Validado y cerrado</th><th>Vencido</th><th>Cumplimiento</th><th>Semáforo</th></tr></thead><tbody>
            @foreach($agencies as $agency)
                @php($agencyLoads=$loads->filter(fn($load)=>$load->agency?->id===$agency->id)) @php($ac=$agencyLoads->where('status','VALIDADO_Y_CERRADO')->count()) @endphp @php($ap=$agencyLoads->where('status','PROGRAMADA')->count()) @endphp @php($ar=$agencyLoads->where('status','REPROGRAMADA')->count()) @endphp @php($av=$agencyLoads->where('status','VENCIDA')->count()) @php($apct=$agencyLoads->count()?round(100*$ac/$agencyLoads->count(),1):0) @php($cls=$av>0?'danger':($apct>=90?'ok':'warn'))
                <tr><td><strong>{{ $agency->name }}</strong></td><td>{{ $ap }}</td><td>{{ $ar }}</td><td>{{ $ac }}</td><td>{{ $av }}</td><td>{{ $apct }}%</td><td><span class="semaforo {{ $cls }}"><span class="dot">●</span>{{ $cls==='ok'?'EN CUMPLIMIENTO':($cls==='warn'?'ATENCIÓN':'INCUMPLIMIENTO') }}</span></td></tr>
            @endforeach
            </tbody></table>
        </div>
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Evolución mensual del cumplimiento</h3><p>Comportamiento del universo contratado por pauta.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportMonthly"></canvas></div></div>
    </section>

    <section class="cr-panel" data-cr-panel="dependencies">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Dependencias</h3><p>Agrupación institucional con subtotal por dirección.</p></div></div><table class="cr-table"><thead><tr><th>Dependencia</th><th>Cargas</th><th>Cerradas</th><th>Vencidas</th><th>Reprogramadas</th><th>Cumplimiento</th><th>Semáforo</th></tr></thead><tbody>
        @foreach($agenciesPerformance as $row) @php($pct=(float)($row['percentage']??0)) @php($late=(int)($row['overdue']??0)) @php($cls=$late>0?'danger':($pct>=90?'ok':'warn'))<tr><td><strong>{{ $row['agency']??'Sin dependencia' }}</strong></td><td>{{ $row['total']??0 }}</td><td>{{ $row['closed']??0 }}</td><td>{{ $late }}</td><td>{{ $row['reprogrammed']??0 }}</td><td>{{ $pct }}%</td><td><span class="semaforo {{ $cls }}"><span class="dot">●</span>{{ $cls==='ok'?'EN CUMPLIMIENTO':($cls==='warn'?'ATENCIÓN':'INCUMPLIMIENTO') }}</span></td></tr>@endforeach
        </tbody></table></div>
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Direcciones / Unidades</h3><p>Desempeño comparativo dentro del universo seleccionado.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportUnits"></canvas></div><table class="cr-table"><thead><tr><th>Dirección / Unidad</th><th>Entregables</th><th>Validados</th><th>Cumplimiento</th></tr></thead><tbody>@foreach($unitsPerformance as $row)<tr><td>{{ $row['unit']??'Sin unidad' }}</td><td>{{ $row['total']??0 }}</td><td>{{ $row['validated']??0 }}</td><td>{{ $row['percentage']??0 }}%</td></tr>@endforeach</tbody></table></div>
    </section>

    <section class="cr-panel" data-cr-panel="tracking">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Seguimiento de cargas</h3><p>Identificación rápida de pendientes, reprogramaciones y vencimientos.</p></div></div><table class="cr-table"><thead><tr><th>Dependencia</th><th>Dirección / unidad</th><th>Orden / referencia</th><th>Pauta</th><th>Estado</th><th>Avance</th><th>Semáforo</th></tr></thead><tbody>
        @forelse($loads as $load) @php($statusCode=$loadStatus($load)) @php($m=$statusMeta($statusCode)) @php($unit=$unitNames($load)->implode(' / ')) @if(in_array($statusCode,['REPROGRAMADA','VENCIDA','PROGRAMADA'],true))<tr><td>{{ $agencyName($load) }}</td><td>{{ $unit ?: '—' }}</td><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $m['label'] }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">●</span>{{ $m['class']==='danger'?'INCUMPLIMIENTO':($m['class']==='warn'?'ATENCIÓN':($m['class']==='info'?'SEGUIMIENTO':'EN CUMPLIMIENTO')) }}</span></td></tr>@endif @empty<tr><td colspan="7" class="text-center text-muted p-4">Sin cargas.</td></tr>@endforelse
        </tbody></table></div>
        <div class="cr-note">La lectura ejecutiva prioriza <strong>VENCIDO</strong> y <strong>REPROGRAMADO</strong> como focos de atención; las cargas PROGRAMADAS permanecen en seguimiento hasta completar su ciclo.</div>
    </section>

    <section class="cr-panel" data-cr-panel="audit">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Auditoría / Detalle</h3><p>Jerarquía documental para revisión y archivo.</p></div></div>
        @forelse($groupedLoads as $agency=>$agencyLoads)
            <div class="cr-band">Dependencia · {{ $agency }}</div>
            @foreach($agencyLoads->groupBy(fn($load)=>$unitNames($load)->first() ?: 'Sin dirección / unidad') as $unit=>$unitLoads)
                <div class="cr-unit-head">Dirección / Unidad · {{ $unit }}</div>
                <table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Fecha apertura</th><th>Fecha entrega</th><th>Fecha validación</th><th>Fecha cierre</th><th>Estado</th></tr></thead><tbody>
                @foreach($unitLoads as $load) @php($statusCode=$loadStatus($load)) @php($m=$statusMeta($statusCode))<tr><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->delivered_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->validated_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->closed_at?->format('d/m/Y H:i') ?: '—' }}</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">●</span>{{ $m['label'] }}</span></td></tr>@endforeach
                </tbody></table>
            @endforeach
        @empty
            <div class="p-4 text-muted">No hay registros para los filtros seleccionados.</div>
        @endforelse
        </div>
    </section>

    @if($canBuildReports)
    <section class="cr-panel" data-cr-panel="builder">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Constructor de Reportes</h3><p>Selecciona el nivel de agrupación antes de exportar.</p></div></div><div class="p-3"><div class="cr-grid-3"><div class="status-card info"><strong>1 · Dependencia</strong><small>Agrupar y subtotalizar por dependencia.</small></div><div class="status-card info"><strong>2 · Dirección / Unidad</strong><small>Separar cada dirección dentro de su dependencia.</small></div><div class="status-card info"><strong>3 · Orden / Pauta</strong><small>Detalle documental de cada carga.</small></div></div><div class="cr-actions"><a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-danger btn-sm">Generar PDF Crystal</a><a href="{{ route('reports.xlsx', request()->query()) }}" class="btn btn-success btn-sm">Generar Excel</a></div></div></div>
    </section>
    @endif
</div>

<script>
(function(){
    const tabs=document.querySelectorAll('[data-cr-tab]');
    const panels=document.querySelectorAll('[data-cr-panel]');
    tabs.forEach(tab=>tab.addEventListener('click',()=>{
        const key=tab.dataset.crTab;
        tabs.forEach(t=>t.classList.toggle('active',t===tab));
        panels.forEach(p=>p.classList.toggle('active',p.dataset.crPanel===key));
        window.dispatchEvent(new Event('resize'));
    }));
})();
</script>

<script type="application/json" data-siget-chart="sigetReportStatus">{!! json_encode(['type'=>'doughnut','labels'=>$statusLabels,'datasets'=>[['label'=>'Cargas','data'=>$statusChartData]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportAgency">{!! json_encode(['type'=>'bar','labels'=>$agencyLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$agencyPct]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportMonthly">{!! json_encode(['type'=>'line','labels'=>$trendLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$trendCompliance],['label'=>'Cierres','data'=>$trendClosed],['label'=>'Cargas','data'=>$trendTotal]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportUnits">{!! json_encode(['type'=>'bar','labels'=>$unitLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$unitPct]]]) !!}</script>
@endsection
