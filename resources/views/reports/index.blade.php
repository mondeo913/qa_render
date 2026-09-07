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

    $statusMeta = function (string $code): array {
        return match ($code) {
            'VALIDADO_Y_CERRADO' => ['class' => 'ok', 'label' => 'VALIDADO Y CERRADO', 'note' => 'Cumplimiento confirmado'],
            'REPROGRAMADA' => ['class' => 'warn', 'label' => 'REPROGRAMADO', 'note' => 'Requiere seguimiento'],
            'VENCIDA' => ['class' => 'danger', 'label' => 'VENCIDO', 'note' => 'Incumplimiento'],
            default => ['class' => 'info', 'label' => 'PROGRAMADO', 'note' => 'En seguimiento'],
        };
    };

    $loadStatus = function ($load): string {
        return $load->status instanceof \BackedEnum ? $load->status->value : (string) $load->status;
    };

    $agencyName = function ($load): string {
        return $load->agency?->name ?: 'Sin dependencia';
    };

    $unitNames = function ($load) {
        return $load->deliverables
            ->map(fn ($deliverable) => $deliverable->organizationalUnit?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    };

    $groupedLoads = $loads->groupBy(fn ($load) => $agencyName($load));
    $statusLabels = array_values($statuses);
    $statusChartData = array_map(
        fn ($code) => (int) ($statusDistribution[$code] ?? 0),
        array_keys($statuses)
    );
    $agencyLabels = $agenciesPerformance->map(fn ($row) => $row['agency'] ?? 'Sin dependencia')->values()->all();
    $agencyPct = $agenciesPerformance->map(fn ($row) => (float) ($row['percentage'] ?? 0))->values()->all();
    $unitLabels = $unitsPerformance->map(fn ($row) => $row['unit'] ?? 'Sin unidad')->values()->all();
    $unitPct = $unitsPerformance->map(fn ($row) => (float) ($row['percentage'] ?? 0))->values()->all();
    $trendLabels = $monthlyTrend->map(fn ($row) => $row['period'] ?? '')->values()->all();
    $trendCompliance = $monthlyTrend->map(fn ($row) => (float) ($row['compliance'] ?? 0))->values()->all();
    $trendClosed = $monthlyTrend->map(fn ($row) => (int) ($row['closed'] ?? 0))->values()->all();
    $trendTotal = $monthlyTrend->map(fn ($row) => (int) ($row['total'] ?? 0))->values()->all();
@endphp

<style>
:root{--cr-navy:#10213b;--cr-navy-2:#183254;--cr-line:#d9e0ea;--cr-soft:#f5f7fa;--cr-text:#1b2638;--cr-muted:#657287;--cr-ok:#20a865;--cr-warn:#db9b17;--cr-danger:#d94a4a;--cr-info:#2e79b9}
.siget-report-shell{color:var(--cr-text)}
.siget-report-top{background:linear-gradient(135deg,var(--cr-navy),#203c63);color:#fff;border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:0 8px 22px rgba(16,33,59,.13)}
.siget-report-top .eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:.64rem;opacity:.72;font-weight:700}.siget-report-top h2{margin:.15rem 0 .3rem;font-weight:800}.siget-report-top p{margin:0;color:#d4dfec;font-size:.75rem}
.cr-toolbar{background:#fff;border:1px solid var(--cr-line);border-radius:12px;padding:14px;margin-bottom:16px}.cr-toolbar .label{font-size:.64rem;text-transform:uppercase;letter-spacing:.05em;color:var(--cr-muted);font-weight:700;margin-bottom:5px}.cr-toolbar .form-select,.cr-toolbar .form-control{border-color:#cfd7e2}
.cr-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}.cr-tab{border:1px solid #cdd6e2;background:#fff;color:var(--cr-navy);border-radius:9px;padding:9px 12px;font-size:.7rem;font-weight:700;cursor:pointer}.cr-tab.active{background:var(--cr-navy);color:#fff;border-color:var(--cr-navy)}
.cr-panel{display:none}.cr-panel.active{display:block}
.cr-paper{background:#fff;border:1px solid var(--cr-line);border-radius:12px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 8px rgba(16,33,59,.04)}
.cr-paper-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid var(--cr-line);background:linear-gradient(180deg,#fff,#f8fafc)}
.cr-paper-head h3{font-size:.96rem;margin:0;color:var(--cr-navy);font-weight:800}.cr-paper-head p{font-size:.68rem;margin:4px 0 0;color:var(--cr-muted)}
.cr-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:16px}.cr-kpi{background:#fff;border:1px solid var(--cr-line);border-top:4px solid var(--cr-info);border-radius:10px;padding:12px 10px}.cr-kpi.ok{border-top-color:var(--cr-ok)}.cr-kpi.warn{border-top-color:var(--cr-warn)}.cr-kpi.danger{border-top-color:var(--cr-danger)}.cr-kpi small{display:block;color:var(--cr-muted);font-size:.63rem;text-transform:uppercase}.cr-kpi strong{display:block;color:var(--cr-navy);font-size:1.35rem;margin-top:4px}
.cr-grid-2{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.cr-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.cr-chart{padding:14px;min-height:280px}.cr-chart.tall{min-height:340px}.cr-chart canvas{width:100%!important;height:100%!important;min-height:240px}
.status-legend{padding:12px;border-left:1px solid var(--cr-line)}.status-card{padding:10px 12px;border:1px solid var(--cr-line);border-left:4px solid var(--cr-info);margin-bottom:8px;border-radius:8px}.status-card.ok{border-left-color:var(--cr-ok)}.status-card.warn{border-left-color:var(--cr-warn)}.status-card.danger{border-left-color:var(--cr-danger)}.status-top{display:flex;justify-content:space-between;align-items:center;color:var(--cr-navy);font-size:.72rem}.status-card small{font-size:.62rem;color:var(--cr-muted)}.status-card .n{font-size:1.1rem;font-weight:800}
.cr-table{width:100%;border-collapse:collapse;font-size:.7rem}.cr-table th{background:var(--cr-navy);color:#fff;padding:8px 9px;text-align:left;font-size:.61rem;text-transform:uppercase}.cr-table td{border-bottom:1px solid #e6ebf1;padding:8px 9px;vertical-align:middle}.cr-table tr:nth-child(even) td{background:#fbfcfd}
.cr-agency{border-top:1px solid var(--cr-line)}.cr-agency-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 14px;background:#edf2f7;color:var(--cr-navy);font-size:.78rem}.cr-agency-head span{font-size:.65rem;color:var(--cr-muted)}.cr-unit-head{padding:8px 14px;background:#f7f9fb;border-top:1px solid var(--cr-line);border-bottom:1px solid var(--cr-line);color:#415167;font-size:.67rem;font-weight:800}.cr-detail{overflow:auto}.cr-band{padding:10px 14px;background:var(--cr-navy);color:#fff;font-size:.72rem;font-weight:800}.cr-note{padding:12px 14px;background:#f8fafc;border:1px solid var(--cr-line);border-radius:8px;color:var(--cr-muted);font-size:.68rem}.semaforo{display:inline-flex;align-items:center;gap:5px;font-size:.6rem;font-weight:800}.semaforo.ok{color:var(--cr-ok)}.semaforo.warn{color:#9b6a00}.semaforo.danger{color:var(--cr-danger)}.semaforo.info{color:var(--cr-info)}.dot{font-size:.6rem}.cr-actions{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
@media(max-width:1100px){.cr-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.cr-grid-2{grid-template-columns:1fr}.cr-grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.cr-kpis{grid-template-columns:1fr 1fr}.cr-grid-3{grid-template-columns:1fr}.cr-tabs{overflow:auto;flex-wrap:nowrap}.cr-tab{white-space:nowrap}}
</style>

<div class="siget-report-shell">
    <div class="siget-report-top">
        <div class="eyebrow">SIGET · Centro de Reportes · Formato Crystal</div>
        <h2>Reportes Ejecutivos y de Auditoría</h2>
        <p>Lectura jerárquica por Dependencia → Dirección / Unidad → Orden / Pauta, con semáforos, subtotales y trazabilidad.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="cr-toolbar">
        <div class="row g-3 align-items-end">
            <div class="col-xl-2 col-md-4"><div class="label">Dependencia</div><select name="agency_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency_id']??'')===(string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
            <div class="col-xl-3 col-md-4"><div class="label">Dirección / Unidad</div><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)($filters['organizational_unit_id']??'')===(string)$unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Estado</div><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($statuses as $code=>$label)<option value="{{ $code }}" @selected(($filters['status']??'')===$code)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><div class="label">Desde</div><input type="month" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-2 col-md-4"><div class="label">Hasta</div><input type="month" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-sm"></div>
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
            <div class="cr-paper"><div class="cr-paper-head"><div><h3>Distribución por estado</h3><p>Los cuatro estados oficiales de lectura ejecutiva.</p></div></div><div class="cr-grid-2" style="grid-template-columns:1fr 1fr;gap:0"><div class="cr-chart"><canvas id="sigetReportStatus"></canvas></div><div class="status-legend">@foreach($statuses as $code=>$label) @php($m=$statusMeta($code))<div class="status-card {{ $m['class'] }}"><div class="status-top"><strong>{{ $label }}</strong><span class="n">{{ (int)($statusDistribution[$code] ?? 0) }}</span></div><small>{{ $m['note'] }}</small></div>@endforeach</div></div></div>
            <div class="cr-paper"><div class="cr-paper-head"><div><h3>Cumplimiento por dependencia</h3><p>Comparación institucional.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportAgency"></canvas></div></div>
        </div>
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Resumen por dependencia</h3><p>Subtotal institucional y semáforo.</p></div><span class="badge text-bg-light">{{ $groupedLoads->count() }} dependencias</span></div>
            @forelse($groupedLoads as $agency=>$agencyLoads)
                @php $agencyKpi=$agenciesPerformance->first(fn($row)=>($row['agency']??'')===$agency); $pct=(float)($agencyKpi['percentage']??0); $late=(int)($agencyKpi['overdue']??0); $byUnit=$agencyLoads->flatMap(fn($load)=>$unitNames($load)->map(fn($unit)=>['unit'=>$unit,'load'=>$load]))->groupBy('unit'); @endphp
                <div class="cr-agency"><div class="cr-agency-head"><strong>{{ $agency }}</strong><span>{{ $agencyLoads->count() }} cargas · {{ number_format($pct,1) }}% cumplimiento</span></div>
                    @foreach($byUnit as $unit=>$entries)
                        <div class="cr-unit-head">Dirección / Unidad: {{ $unit }} · {{ $entries->count() }} cargas</div>
                        <div class="cr-detail"><table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Apertura</th><th>Estado</th><th>Avance</th><th>Semáforo</th></tr></thead><tbody>
                        @foreach($entries as $entry)
                            @php $load=$entry['load']; $statusCode=$loadStatus($load); $m=$statusMeta($statusCode); @endphp
                            <tr><td><strong>{{ $load->title }}</strong></td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y') ?: '—' }}</td><td>{{ $m['label'] }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">●</span>{{ $m['class']==='ok'?'EN CUMPLIMIENTO':($m['class']==='warn'?'ATENCIÓN':($m['class']==='danger'?'INCUMPLIMIENTO':'SEGUIMIENTO')) }}</span></td></tr>
                        @endforeach
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
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Cumplimiento y Desempeño</h3><p>Programado → Reprogramado → Validado y cerrado → Vencido.</p></div></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dependencia</th><th>Programado</th><th>Reprogramado</th><th>Validado y cerrado</th><th>Vencido</th><th>Cumplimiento</th><th>Semáforo</th></tr></thead><tbody>
        @foreach($agencies as $agency)
            @php
                $agencyLoads=$loads->filter(fn($load)=>$load->agency?->id===$agency->id);
                $count=$agencyLoads->count(); $ac=$agencyLoads->where('status','VALIDADO_Y_CERRADO')->count(); $ap=$agencyLoads->where('status','PROGRAMADA')->count(); $ar=$agencyLoads->where('status','REPROGRAMADA')->count(); $av=$agencyLoads->where('status','VENCIDA')->count(); $apct=$count?round(100*$ac/$count,1):0; $cls=$av>0?'danger':($apct>=90?'ok':'warn');
            @endphp
            <tr><td><strong>{{ $agency->name }}</strong></td><td>{{ $ap }}</td><td>{{ $ar }}</td><td>{{ $ac }}</td><td>{{ $av }}</td><td>{{ $apct }}%</td><td><span class="semaforo {{ $cls }}"><span class="dot">●</span>{{ $cls==='ok'?'EN CUMPLIMIENTO':($cls==='warn'?'ATENCIÓN':'INCUMPLIMIENTO') }}</span></td></tr>
        @endforeach
        </tbody></table></div></div>
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Evolución mensual del cumplimiento</h3><p>Comportamiento del universo contratado por periodo.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportMonthly"></canvas></div></div>
    </section>

    <section class="cr-panel" data-cr-panel="dependencies">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Dependencias</h3><p>Resumen por dependencia con lectura de riesgo.</p></div></div><table class="cr-table"><thead><tr><th>Dependencia</th><th>Cargas</th><th>Cerradas</th><th>Vencidas</th><th>Reprogramadas</th><th>Cumplimiento</th><th>Semáforo</th></tr></thead><tbody>
        @forelse($agenciesPerformance as $row)
            @php $pct=(float)($row['percentage']??0); $late=(int)($row['overdue']??0); $cls=$late>0?'danger':($pct>=90?'ok':'warn'); @endphp
            <tr><td><strong>{{ $row['agency']??'Sin dependencia' }}</strong></td><td>{{ $row['total']??0 }}</td><td>{{ $row['closed']??0 }}</td><td>{{ $late }}</td><td>{{ $row['reprogrammed']??0 }}</td><td>{{ $pct }}%</td><td><span class="semaforo {{ $cls }}"><span class="dot">●</span>{{ $cls==='ok'?'EN CUMPLIMIENTO':($cls==='warn'?'ATENCIÓN':'INCUMPLIMIENTO') }}</span></td></tr>
        @empty
            <tr><td colspan="7" class="text-center p-4 text-muted">Sin dependencias para los filtros actuales.</td></tr>
        @endforelse
        </tbody></table></div>
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Direcciones / Unidades</h3><p>Desempeño dentro del universo seleccionado.</p></div></div><div class="cr-chart tall"><canvas id="sigetReportUnits"></canvas></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dirección / Unidad</th><th>Entregables</th><th>Validados</th><th>Cumplimiento</th></tr></thead><tbody>@forelse($unitsPerformance as $row)<tr><td>{{ $row['unit']??'Sin unidad' }}</td><td>{{ $row['total']??0 }}</td><td>{{ $row['validated']??0 }}</td><td>{{ $row['percentage']??0 }}%</td></tr>@empty<tr><td colspan="4" class="text-center p-4 text-muted">Sin información.</td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section class="cr-panel" data-cr-panel="tracking">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Seguimiento de cargas</h3><p>Focos de atención para seguimiento ejecutivo.</p></div></div><div class="table-responsive"><table class="cr-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Orden / referencia</th><th>Pauta</th><th>Estado</th><th>Avance</th><th>Semáforo</th></tr></thead><tbody>
        @forelse($loads as $load)
            @php $statusCode=$loadStatus($load); $m=$statusMeta($statusCode); $unit=$unitNames($load)->implode(' / '); @endphp
            @if(in_array($statusCode,['REPROGRAMADA','VENCIDA','PROGRAMADA'],true))<tr><td>{{ $agencyName($load) }}</td><td>{{ $unit ?: '—' }}</td><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $m['label'] }}</td><td>{{ number_format((float)$load->completion_percentage,0) }}%</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">●</span>{{ $m['class']==='danger'?'INCUMPLIMIENTO':($m['class']==='warn'?'ATENCIÓN':'SEGUIMIENTO') }}</span></td></tr>@endif
        @empty
            <tr><td colspan="7" class="text-center p-4 text-muted">Sin cargas.</td></tr>
        @endforelse
        </tbody></table></div></div>
        <div class="cr-note">La lectura ejecutiva prioriza <strong>VENCIDO</strong> y <strong>REPROGRAMADO</strong> como focos de atención; las cargas PROGRAMADAS permanecen en seguimiento hasta completar su ciclo.</div>
    </section>

    <section class="cr-panel" data-cr-panel="audit">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Auditoría / Detalle</h3><p>Jerarquía documental: Dependencia → Dirección / Unidad → Orden / Pauta.</p></div></div>
        @forelse($groupedLoads as $agency=>$agencyLoads)
            <div class="cr-band">Dependencia · {{ $agency }}</div>
            @foreach($agencyLoads->groupBy(fn($load)=>$unitNames($load)->first() ?: 'Sin dirección / unidad') as $unit=>$unitLoads)
                <div class="cr-unit-head">Dirección / Unidad · {{ $unit }}</div>
                <div class="cr-detail"><table class="cr-table"><thead><tr><th>Orden / referencia</th><th>Pauta / periodo</th><th>Fecha apertura</th><th>Fecha entrega</th><th>Fecha validación</th><th>Fecha cierre</th><th>Estado</th></tr></thead><tbody>
                @foreach($unitLoads as $load)
                    @php $statusCode=$loadStatus($load); $m=$statusMeta($statusCode); @endphp
                    <tr><td>{{ $load->title }}</td><td>{{ $load->period_label ?: '—' }}</td><td>{{ $load->effective_open_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->delivered_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->validated_at?->format('d/m/Y H:i') ?: '—' }}</td><td>{{ $load->closed_at?->format('d/m/Y H:i') ?: '—' }}</td><td><span class="semaforo {{ $m['class'] }}"><span class="dot">●</span>{{ $m['label'] }}</span></td></tr>
                @endforeach
                </tbody></table></div>
            @endforeach
        @empty
            <div class="p-4 text-muted">No hay registros para los filtros seleccionados.</div>
        @endforelse
        </div>
    </section>

    @if($canBuildReports)
    <section class="cr-panel" data-cr-panel="builder">
        <div class="cr-paper"><div class="cr-paper-head"><div><h3>Constructor de Reportes</h3><p>Generación formal del reporte ejecutivo con filtros actuales.</p></div></div><div class="p-3"><div class="cr-grid-3"><div class="status-card info"><strong>1 · Dependencia</strong><small>Agrupar y subtotalizar por dependencia.</small></div><div class="status-card info"><strong>2 · Dirección / Unidad</strong><small>Separar cada dirección dentro de su dependencia.</small></div><div class="status-card info"><strong>3 · Orden / Pauta</strong><small>Detalle documental de cada carga.</small></div></div><div class="cr-actions"><a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-danger btn-sm">Generar PDF Crystal</a><a href="{{ route('reports.xlsx', request()->query()) }}" class="btn btn-success btn-sm">Generar Excel</a></div></div></div>
    </section>
    @endif
</div>

<script>
(function(){
    const tabs=document.querySelectorAll('[data-cr-tab]');
    const panels=document.querySelectorAll('[data-cr-panel]');
    tabs.forEach(function(tab){
        tab.addEventListener('click',function(){
            const key=tab.dataset.crTab;
            tabs.forEach(function(t){t.classList.toggle('active',t===tab);});
            panels.forEach(function(panel){panel.classList.toggle('active',panel.dataset.crPanel===key);});
            window.dispatchEvent(new Event('resize'));
        });
    });
})();
</script>

<script type="application/json" data-siget-chart="sigetReportStatus">{!! json_encode(['type'=>'doughnut','data'=>['labels'=>$statusLabels,'datasets'=>[['label'=>'Cargas','data'=>$statusChartData]]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportAgency">{!! json_encode(['type'=>'bar','data'=>['labels'=>$agencyLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$agencyPct]]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportMonthly">{!! json_encode(['type'=>'line','data'=>['labels'=>$trendLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$trendCompliance],['label'=>'Cierres','data'=>$trendClosed],['label'=>'Cargas','data'=>$trendTotal]]]]) !!}</script>
<script type="application/json" data-siget-chart="sigetReportUnits">{!! json_encode(['type'=>'bar','data'=>['labels'=>$unitLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$unitPct]]]]) !!}</script>
@endsection
