@extends('layouts.app')
@section('title','Dashboard Ejecutivo SIGET')
@section('page-title', $presentation['title'] ?? 'Dashboard Ejecutivo SIGET')
@section('page-subtitle', 'Avance de pautas, cargas y cumplimiento por dependencia')
@section('content')
@php
$role = auth()->user()?->role?->code;
$k = $analytics['kpis'] ?? [];
$pauta = $analytics['pauta_summary'] ?? [];
$agencyRows = collect($analytics['agency_performance'] ?? []);
$directionRows = collect($analytics['direction_performance'] ?? []);
$status = collect($analytics['status_distribution'] ?? []);
$monthly = collect($analytics['monthly_trend'] ?? []);
$total = (int)($pauta['programmed'] ?? $k['total'] ?? 0);
$closed = (int)($pauta['closed'] ?? $k['closed'] ?? 0);
$validated = (int)($pauta['validated'] ?? $k['validated'] ?? 0);
$realized = (int)($pauta['realized'] ?? $k['realized'] ?? 0);
$reprogrammed = (int)($pauta['reprogrammed'] ?? $k['reprogrammed'] ?? 0);
$missing = (int)($pauta['missing'] ?? $k['pending'] ?? 0);
$overdue = (int)($pauta['overdue'] ?? $k['overdue'] ?? 0);
$realizedOpen = max(0, $realized - $closed);
$executionRate = $total ? round(100 * $realized / $total, 1) : 0;
$closureRate = $total ? round(100 * $closed / $total, 1) : 0;
$missingRate = $total ? round(100 * $missing / $total, 1) : 0;
$reprogramRate = $total ? round(100 * $reprogrammed / $total, 1) : 0;
$roleLabel = match($role) {
    'DIRECTOR_GENERAL' => 'DIRECTOR GENERAL',
    'ADMINISTRADOR' => 'ADMINISTRADOR',
    'ENLACE_INSTITUCIONAL' => 'ENLACE INSTITUCIONAL',
    default => $role,
};
@endphp

<style>
.siget-dash{--bg:#0b1118;--panel:#121b24;--panel2:#0f1720;--line:rgba(255,255,255,.09);--text:#eff6fb;--muted:#90a4b6;--cyan:#21c6d8;--green:#35c77a;--blue:#4f7cff;--orange:#f59a65;--red:#ef4655;--yellow:#e9b949;background:linear-gradient(180deg,#0a1017,#0d141c);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:18px;color:var(--text)}
.siget-dash .hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;background:linear-gradient(105deg,#0e2432,#10283a 58%,#0e1823);border:1px solid rgba(33,198,216,.22);border-radius:15px;padding:18px 20px;margin-bottom:14px}.siget-dash .eyebrow{font-size:.67rem;text-transform:uppercase;letter-spacing:.14em;color:#6ddde8;font-weight:800}.siget-dash h2{font-size:1.4rem;color:#fff;margin:.2rem 0}.siget-dash .subtitle{color:#a5b7c5;font-size:.73rem}.siget-dash .scope{min-width:170px;text-align:right}.siget-dash .scope small{color:#8fa4b6;font-size:.62rem;text-transform:uppercase;letter-spacing:.08em}.siget-dash .scope strong{display:block;font-size:1.7rem;color:#fff;line-height:1.1}.siget-dash .scope span{color:#91a6b8;font-size:.68rem}
.siget-dash .kpi{height:100%;background:linear-gradient(145deg,#121e2a,#101822);border:1px solid var(--line);border-radius:13px;padding:14px 15px;position:relative;overflow:hidden}.siget-dash .kpi:after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--accent,var(--cyan))}.siget-dash .kpi-label{color:var(--muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.04em}.siget-dash .kpi-value{font-size:1.5rem;font-weight:800;margin-top:4px;color:#fff}.siget-dash .kpi-note{font-size:.64rem;color:#7890a3;margin-top:3px}
.siget-dash .panel{height:100%;background:rgba(17,27,37,.93);border:1px solid var(--line);border-radius:14px;overflow:hidden}.siget-dash .head{padding:13px 15px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.siget-dash .head h3{font-size:.9rem;margin:0;color:#fff}.siget-dash .head p{font-size:.64rem;margin:4px 0 0;color:var(--muted)}.siget-dash .head .tag{font-size:.58rem;color:#6bd9e5;white-space:nowrap}.siget-dash .chart{height:310px;padding:12px 14px 10px}.siget-dash .chart canvas{width:100%!important;height:100%!important}.siget-dash .legend-note{font-size:.63rem;color:#8094a6;padding:0 15px 12px}.siget-dash .table-wrap{overflow:auto;max-height:380px}.siget-dash table{width:100%;font-size:.68rem;margin:0;color:#eaf2f7;border-collapse:collapse}.siget-dash th{font-size:.58rem;color:#7890a3;text-transform:uppercase;letter-spacing:.05em;text-align:left;padding:9px;border-bottom:1px solid var(--line);position:sticky;top:0;background:#111b25;z-index:1}.siget-dash td{padding:9px;border-bottom:1px solid var(--line);vertical-align:middle}.siget-dash tr:last-child td{border-bottom:0}.siget-dash .bar{height:7px;border-radius:99px;background:#202c37;overflow:hidden;min-width:90px}.siget-dash .bar>span{display:block;height:100%;border-radius:inherit;background:var(--cyan)}.siget-dash .pill{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:3px 7px;font-size:.57rem;font-weight:700}.siget-dash .pill.good{background:rgba(53,199,122,.12);color:#67da99}.siget-dash .pill.warn{background:rgba(233,185,73,.12);color:#efc96e}.siget-dash .pill.bad{background:rgba(239,70,85,.12);color:#ff7b86}.siget-dash .summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:12px 14px}.siget-dash .summary .item{background:#0e171f;border:1px solid var(--line);border-radius:10px;padding:10px}.siget-dash .summary .item small{display:block;color:#7d93a5;font-size:.58rem}.siget-dash .summary .item strong{display:block;color:#fff;font-size:1.1rem;margin-top:2px}.siget-dash .footnote{font-size:.62rem;color:#778b9c;padding:0 14px 13px}.siget-dash .filters-note{font-size:.64rem;color:#8296a7;margin:-5px 0 12px}.siget-dash .mobile-scroll{overflow-x:auto}
@media (max-width: 900px){.siget-dash .hero{display:block}.siget-dash .scope{text-align:left;margin-top:10px}.siget-dash .chart{height:280px}}
</style>

<div class="siget-dash">
    <div class="hero">
        <div>
            <div class="eyebrow">SIGET · {{ $roleLabel }}</div>
            <h2>Avance de pautas y cargas contratadas</h2>
            <div class="subtitle">Las mismas tres gráficas se muestran para Administrador, Director General y Enlace Institucional, respetando el alcance de información de cada usuario.</div>
        </div>
        <div class="scope"><small>Órdenes / cargas del universo</small><strong>{{ number_format($total) }}</strong><span>según las pautas y filtros seleccionados</span></div>
    </div>

    @include('dashboard.partials.filters')
    <div class="filters-note">Dependencia + Dirección/Unidad + rango mensual de fechas contratadas. Al aplicar filtros, los KPI, gráficas y tabla se recalculan sobre el mismo universo.</div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:var(--cyan)"><div class="kpi-label">Programadas</div><div class="kpi-value">{{ $total }}</div><div class="kpi-note">Órdenes de la pauta seleccionada</div></div></div>
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:var(--green)"><div class="kpi-label">Realizadas</div><div class="kpi-value">{{ $realized }}</div><div class="kpi-note">{{ $executionRate }}% de las programadas</div></div></div>
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:#6fe0a0"><div class="kpi-label">Validadas</div><div class="kpi-value">{{ $validated }}</div><div class="kpi-note">Con validación registrada</div></div></div>
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:var(--blue)"><div class="kpi-label">Validadas y cerradas</div><div class="kpi-value">{{ $closed }}</div><div class="kpi-note">{{ $closureRate }}% con cierre definitivo</div></div></div>
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:var(--yellow)"><div class="kpi-label">Reprogramadas</div><div class="kpi-value">{{ $reprogrammed }}</div><div class="kpi-note">{{ $reprogramRate }}% a reprogramar / reprogramadas</div></div></div>
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:var(--red)"><div class="kpi-label">Faltantes</div><div class="kpi-value">{{ $missing }}</div><div class="kpi-note">{{ $missingRate }}% aún sin realizar</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="panel">
                <div class="head"><div><h3>1. Estado de las cargas</h3><p>Cuántas están cerradas, realizadas, reprogramadas y faltantes.</p></div><span class="tag">PAUTA</span></div>
                <div class="chart"><canvas id="sigetStatusChart"></canvas></div>
                <div class="summary">
                    <div class="item"><small>Validadas y cerradas</small><strong>{{ $closed }}</strong></div>
                    <div class="item"><small>Realizadas sin cierre</small><strong>{{ $realizedOpen }}</strong></div>
                    <div class="item"><small>Reprogramadas</small><strong>{{ $reprogrammed }}</strong></div>
                    <div class="item"><small>Faltantes</small><strong>{{ $missing }}</strong></div>
                </div>
                <div class="footnote">Una carga reprogramada se mantiene como incidencia de programación; el avance se interpreta junto con la tabla por dependencia.</div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="panel">
                <div class="head"><div><h3>2. Avance por dependencia</h3><p>Programadas vs realizadas, cerradas, reprogramadas y faltantes para cada dependencia.</p></div><span class="tag">DEPENDENCIA</span></div>
                <div class="chart"><canvas id="sigetAgencyChart"></canvas></div>
                <div class="legend-note">El porcentaje mostrado es avance de realización: realizadas ÷ programadas. Las cargas validadas y cerradas se muestran por separado en el detalle.</div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="panel">
                <div class="head"><div><h3>3. Programadas vs realizadas por periodo de pauta</h3><p>Permite ver dónde se acumulan faltantes y en qué meses se está cumpliendo.</p></div><span class="tag">RANGO CONTRATADO</span></div>
                <div class="chart"><canvas id="sigetTrendChart"></canvas></div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="panel">
                <div class="head"><div><h3>Detalle por Dirección / Unidad</h3><p>Avance de las órdenes contratadas que pertenecen a cada dirección.</p></div><span class="tag">DIRECCIÓN</span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Dirección</th><th>Pautadas</th><th>Real.</th><th>Cerr.</th><th>Reprog.</th><th>Falt.</th></tr></thead>
                        <tbody>
                        @forelse($directionRows as $row)
                            @php $pct=(float)($row['percentage']??0); @endphp
                            <tr>
                                <td><strong>{{ $row['unit'] ?? 'Sin dirección' }}</strong><div class="bar mt-1"><span style="width:{{ min(100,max(0,$pct)) }}%"></span></div></td>
                                <td>{{ $row['programmed'] ?? 0 }}</td>
                                <td>{{ $row['realized'] ?? 0 }}</td>
                                <td>{{ $row['closed'] ?? 0 }}</td>
                                <td>{{ $row['reprogrammed'] ?? 0 }}</td>
                                <td>{{ $row['missing'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4">Sin direcciones para el filtro seleccionado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="footnote">El avance de dirección se calcula sobre cargas únicas asociadas a su unidad.</div>
            </div>
        </div>

        <div class="col-12">
            <div class="panel">
                <div class="head"><div><h3>Ordenes contratadas según sus pautas</h3><p>Visión de control por dependencia: qué se programó, qué ya se realizó, qué está validado/cerrado, qué se reprogramó y qué falta.</p></div><span class="tag">CONTROL DIRECTIVO</span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Dependencia</th><th>Programadas</th><th>Realizadas</th><th>Validadas</th><th>Validadas y cerradas</th><th>Reprogramadas</th><th>Faltantes</th><th>Avance</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse($agencyRows as $row)
                            @php $pct=(float)($row['percentage']??0); $falt=(int)($row['missing']??0); $rep=(int)($row['reprogrammed']??0); $estado=$falt>0?'Seguimiento':($rep>0?'Reprogramación':'Favorable'); @endphp
                            <tr>
                                <td><strong>{{ $row['agency'] ?? 'Sin dependencia' }}</strong></td>
                                <td>{{ $row['programmed'] ?? 0 }}</td>
                                <td>{{ $row['realized'] ?? 0 }}</td>
                                <td>{{ $row['validated'] ?? 0 }}</td>
                                <td>{{ $row['closed'] ?? 0 }}</td>
                                <td>{{ $rep }}</td>
                                <td>{{ $falt }}</td>
                                <td style="min-width:150px"><div class="d-flex align-items-center gap-2"><div class="bar flex-grow-1"><span style="width:{{ min(100,max(0,$pct)) }}%"></span></div><small>{{ $pct }}%</small></div></td>
                                <td><span class="pill {{ $falt>0?'bad':($rep>0?'warn':'good') }}">{{ $estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-4">Sin dependencias para el filtro seleccionado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- IMPORTANTE: app.js ya importa Chart.js y llama renderCharts() en DOMContentLoaded.
     Estos bloques JSON se procesan DESPUÉS de que el módulo Chart.js está disponible,
     evitando que las gráficas se queden vacías por una carrera de carga. --}}
<script type="application/json" data-siget-chart="sigetStatusChart">
{!! json_encode([
    'type' => 'doughnut',
    'labels' => ['Validadas y cerradas','Realizadas sin cierre','Reprogramadas','Faltantes'],
    'datasets' => [[
        'data' => [$closed,$realizedOpen,$reprogrammed,$missing],
        'backgroundColor' => ['#4f7cff','#21c6d8','#e9b949','#ef4655'],
        'borderColor' => '#121b24',
        'borderWidth' => 3,
    ]],
    'options' => [
        'cutout' => '62%',
        'plugins' => ['legend' => ['position' => 'bottom']],
    ],
]) !!}
</script>

<script type="application/json" data-siget-chart="sigetAgencyChart">
{!! json_encode([
    'type' => 'bar',
    'labels' => $agencyRows->pluck('agency')->values()->all(),
    'datasets' => [
        ['label'=>'Programadas','data'=>$agencyRows->pluck('programmed')->values()->all(),'backgroundColor'=>'#334454','borderRadius'=>5],
        ['label'=>'Realizadas','data'=>$agencyRows->pluck('realized')->values()->all(),'backgroundColor'=>'#21c6d8','borderRadius'=>5],
        ['label'=>'Validadas y cerradas','data'=>$agencyRows->pluck('closed')->values()->all(),'backgroundColor'=>'#4f7cff','borderRadius'=>5],
        ['label'=>'Reprogramadas','data'=>$agencyRows->pluck('reprogrammed')->values()->all(),'backgroundColor'=>'#e9b949','borderRadius'=>5],
        ['label'=>'Faltantes','data'=>$agencyRows->pluck('missing')->values()->all(),'backgroundColor'=>'#ef4655','borderRadius'=>5],
    ],
    'options' => [
        'indexAxis' => 'y',
        'scales' => [
            'x' => ['beginAtZero'=>true],
            'y' => ['grid'=>['display'=>false]],
        ],
    ],
]) !!}
</script>

<script type="application/json" data-siget-chart="sigetTrendChart">
{!! json_encode([
    'type' => 'bar',
    'labels' => $monthly->pluck('period')->values()->all(),
    'datasets' => [
        ['label'=>'Programadas','data'=>$monthly->pluck('total')->values()->all(),'backgroundColor'=>'#334454','borderRadius'=>5],
        ['label'=>'Realizadas','data'=>$monthly->pluck('realized')->values()->all(),'backgroundColor'=>'#21c6d8','borderRadius'=>5],
        ['label'=>'Validadas y cerradas','data'=>$monthly->pluck('closed')->values()->all(),'backgroundColor'=>'#4f7cff','borderRadius'=>5],
        ['label'=>'Cumplimiento %','type'=>'line','data'=>$monthly->pluck('compliance')->values()->all(),'borderColor'=>'#35c77a','backgroundColor'=>'#35c77a','yAxisID'=>'y1','tension'=>.25,'pointRadius'=>3,'pointHoverRadius'=>5],
    ],
    'options' => [
        'scales' => [
            'x' => ['grid'=>['display'=>false]],
            'y' => ['beginAtZero'=>true],
            'y1' => ['position'=>'right','beginAtZero'=>true,'max'=>100,'grid'=>['drawOnChartArea'=>false]],
        ],
    ],
]) !!}
</script>
@endsection
