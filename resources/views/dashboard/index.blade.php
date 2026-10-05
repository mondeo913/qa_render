@extends('layouts.app')
@section('title','Dashboard operativo SIGET')
@section('page-title','Dashboard operativo')
@section('page-subtitle', auth()->user()?->organizationalUnit?->name ?: ($presentation['scope'] ?? 'Operación'))

@section('content')
@php
    $role = auth()->user()?->role?->code;
    $k = $analytics['kpis'] ?? [];
    $filters = $filters ?? [];
    $evidenceSummary = $analytics['evidence_summary'] ?? [];
    $unitEvidence = collect($analytics['evidence_by_unit'] ?? []);
    $trend = collect($analytics['evidence_trend'] ?? []);
    $responsibles = collect($analytics['evidence_by_responsible'] ?? []);
    $riskItems = collect($analytics['risk_items'] ?? []);
    $upcoming = collect($analytics['upcoming'] ?? []);
    $tableRows = $riskItems->merge($upcoming)->filter(fn($row) => $row?->id)->unique('id')->sortBy(fn($row) => $row->effective_close_at?->timestamp ?? PHP_INT_MAX)->take(10)->values();

    $total = (int)($k['total'] ?? 0);
    $expected = (int)($k['evidence_expected'] ?? $evidenceSummary['expected'] ?? 0);
    $received = (int)($k['evidence_received'] ?? $evidenceSummary['received'] ?? 0);
    $pending = (int)($k['pending'] ?? max(0, $expected - $received));
    $observed = (int)($k['observed'] ?? $evidenceSummary['observed'] ?? 0);
    $overdue = (int)($k['overdue'] ?? 0);
    $review = (int)($k['review_pending'] ?? $evidenceSummary['review'] ?? 0);
    $submissionRate = $expected ? round(100 * $received / $expected) : 0;

    $statusMap = collect([
        'PROGRAMADA' => 'Programado',
        'ABIERTA' => 'Abierto',
        'EN_CAPTURA' => 'En captura',
        'PARCIALMENTE_ENTREGADA' => 'Entrega parcial',
        'ENTREGADA' => 'Enviado',
        'EN_REVISION_INSTITUCIONAL' => 'En revisión',
        'OBSERVADA' => 'Observada',
        'VALIDADA' => 'Validado',
        'VALIDADO_Y_CERRADO' => 'Cerrado',
        'REPROGRAMADA' => 'Reprogramado',
        'REPROGRAMADA_ABIERTA' => 'Reprogramado',
        'REPROGRAMADA_ENTREGADA' => 'Enviado',
        'VENCIDA' => 'Vencida',
    ]);

    $funnel = [
        ['label' => 'Programado', 'value' => $expected, 'class' => 'navy'],
        ['label' => 'Enviado', 'value' => $received, 'class' => 'teal'],
        ['label' => 'En revisión', 'value' => (int)($evidenceSummary['review'] ?? $review), 'class' => 'amber'],
        ['label' => 'Validado', 'value' => (int)($evidenceSummary['validated'] ?? 0), 'class' => 'purple'],
        ['label' => 'Cerrado', 'value' => (int)($analytics['evidence_funnel']['CERRADO'] ?? 0), 'class' => 'green'],
    ];

    $calendarYm = $filters['from'] ?? $filters['to'] ?? ($trend->last()['period'] ?? now()->format('Y-m'));
    $calendarBase = date_create($calendarYm . '-01') ?: date_create('first day of this month');
    $calendarDays = (int)$calendarBase->format('t');
    $calendarStart = (int)$calendarBase->format('N') - 1;
    $calendarSignals = [];
    foreach ($tableRows as $row) {
        $dateKey = $row->effective_close_at?->format('Y-m-d');
        if (!$dateKey) continue;
        $rawStatus = data_get($row, 'status.value', $row->status);
        $calendarSignals[$dateKey][] = (string)$rawStatus;
    }
    $calendarCells = [];
    for ($i = 0; $i < $calendarStart; $i++) $calendarCells[] = ['day' => null, 'tone' => ''];
    for ($day = 1; $day <= $calendarDays; $day++) {
        $dateKey = $calendarBase->format('Y-m-') . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
        $states = $calendarSignals[$dateKey] ?? [];
        $tone = '';
        if (collect($states)->contains(fn($s) => $s === 'VENCIDA')) $tone = 'danger';
        elseif (collect($states)->contains(fn($s) => in_array($s, ['ENTREGADA','EN_REVISION_INSTITUCIONAL','OBSERVADA','VALIDADA'], true))) $tone = 'active';
        elseif ($states) $tone = 'next';
        $calendarCells[] = ['day' => $day, 'tone' => $tone, 'today' => now()->format('Y-m-d') === $dateKey];
    }

    $scopeLabel = match ($role) {
        'DIRECTOR_TRANSMISION' => 'Dirección de Transmisión',
        'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'Dirección de Programación y Continuidad',
        'OPERADOR_TRANSMISION' => 'Dirección de Transmisión',
        'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'Dirección de Programación y Continuidad',
        'FISCALIZADOR' => 'Fiscalización',
        default => $presentation['scope'] ?? 'Cargas visibles',
    };
@endphp

<style>
.siget-op-dashboard{
    --op-navy:#0d2d57;
    --op-navy-2:#173e70;
    --op-teal:#0f95a2;
    --op-teal-2:#148d96;
    --op-green:#4d904e;
    --op-amber:#e7a118;
    --op-red:#c93333;
    --op-purple:#6650a8;
    --op-text:#1c2f45;
    --op-muted:#68798e;
    --op-line:#e0e6ee;
    --op-bg:#f6f8fb;
    --op-surface:#fff;
    margin:-2px 0 0;
    color:var(--op-text);
}
.siget-op-dashboard .op-heading{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:16px;
    margin:0 2px 14px;
}
.siget-op-dashboard .op-heading-copy h2{margin:0;color:var(--op-navy);font-weight:800;font-size:1.65rem;line-height:1.05}
.siget-op-dashboard .op-heading-copy p{margin:6px 0 0;color:var(--op-teal);font-weight:700;font-size:.95rem}
.siget-op-dashboard .op-heading-meta{color:var(--op-muted);font-size:.72rem;text-align:right}
.siget-op-dashboard .op-filterbar{
    display:grid;
    grid-template-columns:1.05fr 1.35fr 1.05fr 1.05fr auto;
    gap:10px;
    align-items:center;
    margin-bottom:14px;
}
.siget-op-dashboard .op-filter-control{
    min-width:0;
    height:42px;
    display:flex;
    align-items:center;
    gap:9px;
    padding:0 12px;
    background:var(--op-surface);
    border:1px solid #d8e1ec;
    border-radius:9px;
    box-shadow:0 2px 8px rgba(20,49,83,.05);
}
.siget-op-dashboard .op-filter-control i{color:var(--op-muted);font-size:1rem;flex:0 0 auto}
.siget-op-dashboard .op-filter-control select{
    width:100%;
    min-width:0;
    border:0;
    outline:0;
    box-shadow:none!important;
    background:transparent!important;
    color:#22354b;
    font-size:.76rem;
    padding:0;
}
.siget-op-dashboard .op-filter-control select:focus{box-shadow:none!important}
.siget-op-dashboard .op-filter-actions{display:flex;gap:8px;align-items:center}
.siget-op-dashboard .op-filter-actions .btn{height:42px;padding:0 15px;border-radius:9px;font-size:.76rem;font-weight:700}
.siget-op-dashboard .op-filter-actions .btn-outline-primary{border-color:#5fb0bb;color:#0b7580;background:#fff}
.siget-op-dashboard .op-advanced{
    margin:-4px 0 14px;
    border:1px solid var(--op-line);
    border-radius:9px;
    background:#fff;
    box-shadow:0 2px 8px rgba(20,49,83,.04);
}
.siget-op-dashboard .op-advanced .row{padding:10px 12px}
.siget-op-dashboard .op-advanced .form-label{font-size:.66rem;font-weight:700;color:var(--op-muted);margin-bottom:4px}
.siget-op-dashboard .op-advanced .form-control,.siget-op-dashboard .op-advanced .form-select{height:34px;font-size:.72rem}

.siget-op-dashboard .op-kpis{
    display:grid;
    grid-template-columns:repeat(8,minmax(0,1fr));
    gap:7px;
    margin-bottom:13px;
}
.siget-op-dashboard .op-kpi{
    min-width:0;
    height:98px;
    padding:10px 11px;
    background:var(--op-surface);
    border:1px solid var(--op-line);
    border-radius:11px;
    box-shadow:0 2px 8px rgba(20,49,83,.05);
    display:grid;
    grid-template-columns:37px 1fr;
    grid-template-rows:auto 1fr;
    column-gap:9px;
    align-items:center;
}
.siget-op-dashboard .op-kpi-icon{
    grid-row:1 / span 2;
    width:37px;height:37px;border-radius:50%;
    display:grid;place-items:center;
    color:#fff;font-size:1.05rem;
}
.siget-op-dashboard .op-kpi-label{
    font-size:.69rem;line-height:1.08;color:#3a4b61;
    overflow-wrap:anywhere;
}
.siget-op-dashboard .op-kpi-value{
    display:flex;align-items:flex-end;gap:6px;
    color:var(--op-navy);font-weight:800;font-size:1.45rem;line-height:1;
}
.siget-op-dashboard .op-kpi-meta{font-size:.62rem;color:var(--op-muted);font-weight:600}
.siget-op-dashboard .op-kpi-progress{margin-top:4px;height:4px;border-radius:5px;background:#edf1f5;overflow:hidden}
.siget-op-dashboard .op-kpi-progress span{display:block;height:100%;background:var(--op-teal)}

.siget-op-dashboard .op-grid-top{
    display:grid;
    grid-template-columns:1.15fr .85fr 1.1fr;
    gap:11px;
    margin-bottom:11px;
}
.siget-op-dashboard .op-grid-bottom{
    display:grid;
    grid-template-columns:1.02fr .88fr 1.75fr;
    gap:11px;
}
.siget-op-dashboard .op-card{
    min-width:0;
    background:var(--op-surface);
    border:1px solid var(--op-line);
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 2px 8px rgba(20,49,83,.045);
}
.siget-op-dashboard .op-card-head{
    padding:10px 13px 7px;
}
.siget-op-dashboard .op-card-head h3{
    margin:0;color:var(--op-navy);font-size:.9rem;line-height:1.15;font-weight:800;
}
.siget-op-dashboard .op-card-head p{
    margin:3px 0 0;color:var(--op-muted);font-size:.65rem;
}
.siget-op-dashboard .op-card-body{padding:0 13px 11px}
.siget-op-dashboard .op-chart{height:188px}
.siget-op-dashboard .op-chart canvas{width:100%!important;height:100%!important}

.siget-op-dashboard .op-bar-list{display:grid;gap:11px;padding:6px 0 4px}
.siget-op-dashboard .op-bar-row{display:grid;grid-template-columns:minmax(64px,1fr) minmax(95px,2fr) 30px;gap:8px;align-items:center;font-size:.67rem;color:#50637a}
.siget-op-dashboard .op-bar-track{height:11px;background:#edf1f5;border-radius:3px;overflow:hidden}
.siget-op-dashboard .op-bar-stack{height:100%;display:flex}
.siget-op-dashboard .op-bar-stack span{height:100%}
.siget-op-dashboard .op-bar-value{text-align:right;font-weight:700;color:#3e5067}
.siget-op-dashboard .op-unit-legend,.siget-op-dashboard .op-calendar-legend{
    display:flex;flex-wrap:wrap;gap:10px;margin:6px 0 8px;color:var(--op-muted);font-size:.61rem
}
.siget-op-dashboard .op-legend-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:4px;vertical-align:-1px}
.siget-op-dashboard .op-legend-navy{background:var(--op-navy)}
.siget-op-dashboard .op-legend-teal{background:var(--op-teal)}
.siget-op-dashboard .op-legend-gray{background:#cad0d8}
.siget-op-dashboard .op-legend-amber{background:var(--op-amber)}
.siget-op-dashboard .op-legend-red{background:var(--op-red)}
.siget-op-dashboard .op-legend-outline{background:#fff;border:1px solid #9ba8b6}

.siget-op-dashboard .op-calendar{padding:3px 0 0}
.siget-op-dashboard .op-calendar-head{
    display:grid;grid-template-columns:28px 1fr 28px;align-items:center;
    margin-bottom:7px
}
.siget-op-dashboard .op-calendar-head .title{text-align:center;font-size:.71rem;font-weight:800;color:#2e4055;text-transform:capitalize}
.siget-op-dashboard .op-calendar-head button{width:28px;height:28px;border:0;background:transparent;color:#53687e;border-radius:7px}
.siget-op-dashboard .op-calendar-head button:hover{background:#f1f4f7}
.siget-op-dashboard .op-calendar-week{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;font-size:.59rem;text-align:center;color:#6e7d90;font-weight:700;margin-bottom:3px}
.siget-op-dashboard .op-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.siget-op-dashboard .op-day{min-height:24px;border-radius:6px;display:grid;place-items:center;position:relative;font-size:.64rem;color:#3d4e64}
.siget-op-dashboard .op-day.muted{color:#b8c0ca}
.siget-op-dashboard .op-day.today{background:#d7dadd;color:#27384e;font-weight:800}
.siget-op-dashboard .op-day .signal{position:absolute;left:50%;bottom:2px;transform:translateX(-50%);width:5px;height:5px;border-radius:50%}
.siget-op-dashboard .signal.active{background:var(--op-teal)}
.siget-op-dashboard .signal.next{background:var(--op-amber)}
.siget-op-dashboard .signal.danger{background:var(--op-red)}

.siget-op-dashboard .op-funnel-wrap{display:grid;grid-template-columns:1fr 146px;gap:10px;align-items:center}
.siget-op-dashboard .op-funnel{display:grid;place-items:center;gap:1px}
.siget-op-dashboard .op-funnel-stage{height:35px;display:grid;place-items:center;position:relative;clip-path:polygon(0 0,100% 0,89% 100%,11% 100%);color:#fff;font-size:.67rem;font-weight:800}
.siget-op-dashboard .op-funnel-stage.navy{width:100%;background:var(--op-navy)}
.siget-op-dashboard .op-funnel-stage.teal{width:84%;background:var(--op-teal)}
.siget-op-dashboard .op-funnel-stage.amber{width:68%;background:var(--op-amber)}
.siget-op-dashboard .op-funnel-stage.purple{width:52%;background:var(--op-purple)}
.siget-op-dashboard .op-funnel-stage.green{width:38%;background:var(--op-green)}
.siget-op-dashboard .op-funnel-stage span:last-child{margin-left:8px}
.siget-op-dashboard .op-funnel-table{font-size:.6rem}
.siget-op-dashboard .op-funnel-table .rowline{display:grid;grid-template-columns:1fr 43px;gap:6px;padding:9px 0;border-bottom:1px solid #edf0f4}
.siget-op-dashboard .op-funnel-table .rowline:last-child{border-bottom:0}
.siget-op-dashboard .op-funnel-table .qty{text-align:right;font-weight:700}
.siget-op-dashboard .op-funnel-table .pct{text-align:right;color:var(--op-muted);font-weight:600}

.siget-op-dashboard .op-trend{height:220px}
.siget-op-dashboard .op-trend canvas{width:100%!important;height:100%!important}
.siget-op-dashboard .op-responsible-list{display:grid;gap:9px;padding-top:6px}
.siget-op-dashboard .op-resp-row{display:grid;grid-template-columns:88px 1fr 26px;gap:7px;align-items:center;font-size:.66rem;color:#50637a}
.siget-op-dashboard .op-resp-track{height:13px;border-radius:3px;background:#edf1f5;overflow:hidden}
.siget-op-dashboard .op-resp-track span{display:block;height:100%;background:var(--op-teal)}
.siget-op-dashboard .op-resp-row strong{text-align:right;color:#42546b;font-size:.65rem}

.siget-op-dashboard .op-table-card{padding:0}
.siget-op-dashboard .op-evidence-table{width:100%;border-collapse:collapse;font-size:.62rem}
.siget-op-dashboard .op-evidence-table th{background:#f7f9fb;color:#64758a;text-transform:none;font-size:.62rem;font-weight:800;padding:7px 8px;text-align:left;white-space:nowrap}
.siget-op-dashboard .op-evidence-table td{padding:7px 8px;border-top:1px solid #edf0f4;color:#34475e;vertical-align:middle}
.siget-op-dashboard .op-evidence-table .evidence-name{font-weight:700;color:#243850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px}
.siget-op-dashboard .op-state{
    display:inline-flex;align-items:center;justify-content:center;
    padding:4px 7px;border-radius:6px;font-size:.58rem;font-weight:800;white-space:nowrap
}
.siget-op-dashboard .state-red{background:#fde8e8;color:#bd2f2f}
.siget-op-dashboard .state-amber{background:#fff1d5;color:#a56d00}
.siget-op-dashboard .state-teal{background:#e2f4f3;color:#147f87}
.siget-op-dashboard .state-green{background:#e6f3e7;color:#3e7e40}
.siget-op-dashboard .state-purple{background:#eee9fb;color:#5d4d9b}
.siget-op-dashboard .op-table-actions{display:flex;gap:5px;white-space:nowrap}
.siget-op-dashboard .op-table-actions .btn{height:27px;padding:0 8px;border-radius:5px;font-size:.58rem;font-weight:700}
.siget-op-dashboard .op-table-actions .btn-outline-primary{border-color:#7e95ad;color:#23466e}
.siget-op-dashboard .op-table-actions .btn-outline-danger{border-color:#e39a9a;color:#bf3333}

.siget-op-dashboard .op-footer{
    margin-top:13px;padding:8px 0 0;border-top:1px solid #e7ebf1;
    text-align:center;color:#8a96a7;font-size:.65rem
}
.siget-op-dashboard .op-scope-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 8px;border-radius:7px;background:#eff7f8;color:#137a85;font-size:.63rem;font-weight:800
}

@media (max-width:1500px){
  .siget-op-dashboard .op-filterbar{grid-template-columns:repeat(4,minmax(0,1fr))}
  .siget-op-dashboard .op-filter-actions{grid-column:1 / -1;justify-content:flex-end}
  .siget-op-dashboard .op-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}
}
@media (max-width:1100px){
  .siget-op-dashboard .op-grid-top,.siget-op-dashboard .op-grid-bottom{grid-template-columns:1fr}
  .siget-op-dashboard .op-filterbar{grid-template-columns:repeat(2,minmax(0,1fr))}
  .siget-op-dashboard .op-filter-actions{grid-column:auto}
  .siget-op-dashboard .op-funnel-wrap{grid-template-columns:1fr}
}
@media (max-width:700px){
  .siget-op-dashboard .op-heading{display:block}
  .siget-op-dashboard .op-heading-meta{text-align:left;margin-top:7px}
  .siget-op-dashboard .op-filterbar{grid-template-columns:1fr}
  .siget-op-dashboard .op-filter-actions{grid-column:auto;justify-content:stretch}
  .siget-op-dashboard .op-filter-actions .btn{flex:1}
  .siget-op-dashboard .op-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  .siget-op-dashboard .op-kpi{height:91px}
  .siget-op-dashboard .op-card-head{padding:10px}
  .siget-op-dashboard .op-card-body{padding:0 10px 10px}
  .siget-op-dashboard .op-chart{height:210px}
  .siget-op-dashboard .op-evidence-table{min-width:700px}
}

/* Mismo lenguaje visual en claro y oscuro sin alterar la información ni los permisos. */
html[data-bs-theme="dark"] .siget-op-dashboard{
    --op-bg:#0f151c;--op-surface:#151c24;--op-text:#eaf1f6;--op-muted:#91a3b6;--op-line:#2e3b49;
    --op-navy:#79b4df;--op-navy-2:#83c4d0;--op-teal:#44c1c7;--op-green:#70bb72;--op-amber:#eab44a;--op-red:#ed6a6a;--op-purple:#9d89d6;
}
html[data-bs-theme="dark"] .siget-op-dashboard .op-heading-copy h2,
html[data-bs-theme="dark"] .siget-op-dashboard .op-card-head h3{color:#eef6fb}
html[data-bs-theme="dark"] .siget-op-dashboard .op-filter-control,
html[data-bs-theme="dark"] .siget-op-dashboard .op-advanced,
html[data-bs-theme="dark"] .siget-op-dashboard .op-kpi,
html[data-bs-theme="dark"] .siget-op-dashboard .op-card{background:var(--op-surface);border-color:var(--op-line)}
html[data-bs-theme="dark"] .siget-op-dashboard .op-filter-control select{color:#e6eef4}
html[data-bs-theme="dark"] .siget-op-dashboard .op-evidence-table th{background:#19232e;color:#b1c0ce}
html[data-bs-theme="dark"] .siget-op-dashboard .op-evidence-table td{border-color:#2c3845;color:#d9e5ed}
html[data-bs-theme="dark"] .siget-op-dashboard .op-evidence-table .evidence-name{color:#f1f6fa}
html[data-bs-theme="dark"] .siget-op-dashboard .op-kpi-label{color:#c0ccd6}
html[data-bs-theme="dark"] .siget-op-dashboard .op-day{color:#dae5ed}
html[data-bs-theme="dark"] .siget-op-dashboard .op-calendar-head .title{color:#eaf3f8}
html[data-bs-theme="dark"] .siget-op-dashboard .op-bar-track,
html[data-bs-theme="dark"] .siget-op-dashboard .op-resp-track{background:#26323e}
</style>

<div class="siget-op-dashboard">
    <div class="op-heading">
        <div class="op-heading-copy">
            <h2>Dashboard operativo</h2>
            <p>{{ $scopeLabel }}</p>
        </div>
        <div class="op-heading-meta">
            <span class="op-scope-badge"><i class="bi bi-shield-check"></i> Alcance autorizado</span>
            <div class="mt-1">Los indicadores y gráficas se calculan únicamente con el universo visible para este usuario.</div>
        </div>
    </div>

    @include('dashboard.partials.filters')

    <div class="op-kpis">
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#118fa0"><i class="bi bi-broadcast-pin"></i></div>
            <div class="op-kpi-label">Spots asignados</div>
            <div>
                <div class="op-kpi-value">{{ number_format($total) }}</div>
            </div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#13a2b1"><i class="bi bi-clipboard-check"></i></div>
            <div class="op-kpi-label">Evidencias esperadas</div>
            <div><div class="op-kpi-value">{{ number_format($expected) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#4f9051"><i class="bi bi-cloud-arrow-up"></i></div>
            <div class="op-kpi-label">Evidencias enviadas</div>
            <div><div class="op-kpi-value">{{ number_format($received) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#113a68"><i class="bi bi-pie-chart-fill"></i></div>
            <div class="op-kpi-label">Cumplimiento de envío</div>
            <div>
                <div class="op-kpi-value">{{ $submissionRate }}%</div>
                <div class="op-kpi-progress"><span style="width:{{ min(100,$submissionRate) }}%"></span></div>
            </div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#ec9f00"><i class="bi bi-clock-fill"></i></div>
            <div class="op-kpi-label">Pendientes</div>
            <div><div class="op-kpi-value" style="color:var(--op-amber)">{{ number_format($pending) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#c63131"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="op-kpi-label">Observadas o rechazadas</div>
            <div><div class="op-kpi-value" style="color:var(--op-red)">{{ number_format($observed) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#c63131"><i class="bi bi-calendar-x-fill"></i></div>
            <div class="op-kpi-label">Vencidas</div>
            <div><div class="op-kpi-value" style="color:var(--op-red)">{{ number_format($overdue) }}</div></div>
        </div>
        <div class="op-kpi">
            <div class="op-kpi-icon" style="background:#153f71"><i class="bi bi-search"></i></div>
            <div class="op-kpi-label">Pendientes de validación</div>
            <div><div class="op-kpi-value">{{ number_format($review) }}</div></div>
        </div>
    </div>

    <div class="op-grid-top">
        <section class="op-card">
            <div class="op-card-head">
                <h3>Avance por unidad interna</h3>
                <div class="op-unit-legend">
                    <span><i class="op-legend-dot op-legend-navy"></i>Esperadas</span>
                    <span><i class="op-legend-dot op-legend-teal"></i>Recibidas</span>
                    <span><i class="op-legend-dot op-legend-gray"></i>Pendientes</span>
                </div>
            </div>
            <div class="op-card-body">
                <div class="op-bar-list">
                @forelse($unitEvidence->take(6) as $row)
                    @php
                        $uExpected = (int)($row['expected'] ?? 0);
                        $uReceived = (int)($row['received'] ?? 0);
                        $uPending = (int)($row['pending'] ?? 0);
                        $uMax = max(1,$uExpected);
                    @endphp
                    <div class="op-bar-row">
                        <span>{{ Str::limit($row['unit'] ?? 'Sin unidad',18) }}</span>
                        <div class="op-bar-track">
                            <div class="op-bar-stack">
                                <span style="width:100%;background:var(--op-navy)"></span>
                                <span style="width:0;background:var(--op-teal)"></span>
                            </div>
                            <div style="margin-top:-11px;height:11px;position:relative;overflow:hidden;border-radius:3px">
                                <span style="display:block;height:100%;width:{{ min(100,round(100*$uReceived/$uMax)) }}%;background:var(--op-teal)"></span>
                            </div>
                            <div style="margin-top:-11px;height:11px;position:relative;overflow:hidden;border-radius:3px;pointer-events:none">
                                <span style="display:block;height:100%;width:{{ min(100,round(100*$uPending/$uMax)) }}%;background:#cbd1d8"></span>
                            </div>
                        </div>
                        <span class="op-bar-value">{{ $uExpected }}</span>
                    </div>
                @empty
                    <div class="text-secondary text-center py-5">No hay unidades en el alcance actual.</div>
                @endforelse
                </div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Calendario de fechas programadas</h3>
            </div>
            <div class="op-card-body">
                <div class="op-calendar">
                    <div class="op-calendar-head">
                        <button type="button" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></button>
                        <div class="title">{{ strftime('%B %Y', $calendarBase->getTimestamp()) }}</div>
                        <button type="button" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    <div class="op-calendar-week">
                        <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
                    </div>
                    <div class="op-calendar-grid">
                    @foreach($calendarCells as $cell)
                        <div class="op-day {{ !$cell['day'] ? 'muted' : '' }} {{ ($cell['today'] ?? false) ? 'today' : '' }}">
                            {{ $cell['day'] ?: '' }}
                            @if(!empty($cell['tone']))<span class="signal {{ $cell['tone'] }}"></span>@endif
                        </div>
                    @endforeach
                    </div>
                    <div class="op-calendar-legend">
                        <span><i class="op-legend-dot op-legend-amber"></i>Próximos</span>
                        <span><i class="op-legend-dot op-legend-teal"></i>Activos</span>
                        <span><i class="op-legend-dot op-legend-red"></i>Vencidos</span>
                        <span><i class="op-legend-dot op-legend-outline"></i>Cerrados</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Estado del expediente</h3>
            </div>
            <div class="op-card-body">
                <div class="op-funnel-wrap">
                    <div class="op-funnel">
                        @foreach($funnel as $stage)
                            <div class="op-funnel-stage {{ $stage['class'] }}">
                                <span>{{ $stage['label'] }}</span><span>{{ number_format($stage['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="op-funnel-table">
                        @php $funnelBase=max(1,$expected); @endphp
                        @foreach($funnel as $stage)
                            <div class="rowline">
                                <span>{{ $stage['label'] }}</span>
                                <span class="qty">{{ number_format($stage['value']) }}</span>
                                <span class="pct" style="grid-column:2"> {{ round(100*$stage['value']/$funnelBase) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="op-grid-bottom">
        <section class="op-card">
            <div class="op-card-head">
                <h3>Tendencia de entregas</h3>
                <p>Evidencias esperadas, enviadas y validadas.</p>
            </div>
            <div class="op-card-body">
                <div class="op-trend"><canvas id="sigetOperationalTrend"></canvas></div>
            </div>
        </section>

        <section class="op-card">
            <div class="op-card-head">
                <h3>Carga por responsable</h3>
                <p>Responsables dentro del alcance autorizado.</p>
            </div>
            <div class="op-card-body">
                <div class="op-responsible-list">
                    @forelse($responsibles->take(7) as $row)
                        @php $respExpected=(int)($row['expected']??0); $maxResp=max(1,(int)$responsibles->max('expected')); @endphp
                        <div class="op-resp-row">
                            <span>{{ Str::limit($row['responsible'] ?? 'Sin asignar',16) }}</span>
                            <div class="op-resp-track"><span style="width:{{ min(100,round(100*$respExpected/$maxResp)) }}%"></span></div>
                            <strong>{{ $respExpected }}</strong>
                        </div>
                    @empty
                        <div class="text-secondary text-center py-4">Sin responsables visibles.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="op-card op-table-card">
            <div class="op-card-head">
                <h3>Bandeja de evidencias</h3>
                <p>Acciones informativas; el flujo de entrega y validación permanece en sus rutas existentes.</p>
            </div>
            <div class="table-responsive">
                <table class="op-evidence-table">
                    <thead><tr><th>Evidencia</th><th>Responsable</th><th>Fecha límite</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    @forelse($tableRows as $load)
                        @php
                            $loadStatus = data_get($load,'status.value',$load->status);
                            $statusLabel = $statusMap[$loadStatus] ?? str_replace('_',' ',$loadStatus);
                            $statusClass = match($loadStatus) {
                                'VENCIDA' => 'state-red',
                                'OBSERVADA','RECHAZADO' => 'state-red',
                                'EN_REVISION_INSTITUCIONAL','ENTREGADA','EN_CAPTURA' => 'state-teal',
                                'VALIDADA','VALIDADO_Y_CERRADO' => 'state-purple',
                                default => 'state-amber',
                            };
                            $responsibleNames = $load->deliverables?->pluck('responsibleUser.name')->filter()->unique()->join(', ');
                            $deadline = $load->effective_close_at?->format('d/m/Y');
                        @endphp
                        <tr>
                            <td class="evidence-name" title="{{ $load->title }}">{{ $load->title ?: 'Carga sin nombre' }}</td>
                            <td>{{ Str::limit($responsibleNames ?: 'Sin asignar',24) }}</td>
                            <td class="{{ $loadStatus === 'VENCIDA' ? 'text-danger fw-bold' : '' }}">{{ $deadline ?: '—' }}</td>
                            <td><span class="op-state {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>
                                <div class="op-table-actions">
                                    <a href="{{ route('loads.show',$load) }}" class="btn btn-outline-primary btn-sm">Ver expediente</a>
                                    <a href="{{ route('loads.show',$load) }}" class="btn btn-outline-danger btn-sm">Atender</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay evidencias para mostrar en el alcance actual.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="op-footer">
        Horario del sistema: {{ now()->format('d/m/Y H:i') }} h (Hora del Centro)
    </div>
</div>

<script>
(function(){
    const el = document.getElementById('sigetOperationalTrend');
    if(!el || typeof Chart === 'undefined') return;

    const labels = @json($trend->pluck('period')->values());
    const expected = @json($trend->pluck('expected')->values());
    const received = @json($trend->pluck('received')->values());
    const validated = @json($trend->pluck('validated')->values());

    new Chart(el,{
        type:'line',
        data:{
            labels,
            datasets:[
                {label:'Esperadas',data:expected,borderColor:'#173e70',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5},
                {label:'Enviadas',data:received,borderColor:'#148d96',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5},
                {label:'Validadas',data:validated,borderColor:'#4d904e',backgroundColor:'transparent',tension:.35,borderWidth:2,pointRadius:2.5}
            ]
        },
        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{position:'top',align:'start',labels:{color:'#5e7187',boxWidth:9,font:{size:10}}}},
            scales:{
                x:{ticks:{color:'#738297',font:{size:9}},grid:{color:'#eef2f6'}},
                y:{beginAtZero:true,ticks:{color:'#738297',font:{size:9}},grid:{color:'#eef2f6'}}
            }
        }
    });
})();
</script>
@endsection