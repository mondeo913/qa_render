@extends('layouts.app')
@section('title','Indicadores SIGET')
@section('page-title','Indicadores SIGET')
@section('page-subtitle','Monitoreo y análisis de indicadores institucionales')
@section('content')
@php
    $k = $analytics['kpis'] ?? [];
    $monthly = collect($analytics['monthly_trend'] ?? []);
    $units = collect($analytics['unit_performance'] ?? $analytics['direction_performance'] ?? []);
    $agencies = collect($analytics['agency_performance'] ?? []);
    $status = collect($analytics['status_distribution'] ?? []);
    $total = (int)($k['total'] ?? 0);
    $closed = (int)($k['closed'] ?? 0);
    $active = (int)($k['active'] ?? 0);
    $overdue = (int)($k['overdue'] ?? 0);
    $reprogrammed = (int)($k['reprogrammed'] ?? 0);
    $compliance = (float)($k['compliance'] ?? 0);
    $completion = (float)($k['completion_average'] ?? 0);
    $closure = $total ? round(100*$closed/$total,1) : 0;
    $risk = $total ? round(100*$overdue/$total,1) : 0;
    $stability = max(0, round(100 - ($total ? 100*$reprogrammed/$total : 0),1));
    $selectedIndicator = $filters['indicator'] ?? 'cumplimiento';
    $frequency = $filters['frequency'] ?? 'mensual';

    $isDirectionDirector = in_array(
        auth()->user()?->role?->code,
        [
            'DIRECTOR_TRANSMISION',
            'DIRECTOR_PROGRAMACION_CONTINUIDAD',
        ],
        true
    );

    $indicatorStatuses = $isDirectionDirector
        ? [
            'PROGRAMADA',
            'REPROGRAMADA',
            'VALIDADO_Y_CERRADO',
            'VENCIDA',
        ]
        : [
            'PROGRAMADA',
            'ABIERTA',
            'EN_CAPTURA',
            'ENTREGADA',
            'EN_REVISION_INSTITUCIONAL',
            'OBSERVADA',
            'VALIDADA',
            'VALIDADO_Y_CERRADO',
            'VENCIDA',
            'REPROGRAMADA',
        ];

    $indicatorStatusLabels = [
        'PROGRAMADA' => 'PROGRAMADO',
        'REPROGRAMADA' => 'REPROGRAMADO',
        'VALIDADO_Y_CERRADO' => 'VALIDADO Y CERRADO',
        'VENCIDA' => 'VENCIDO',
        'ABIERTA' => 'ABIERTA',
        'EN_CAPTURA' => 'EN CAPTURA',
        'ENTREGADA' => 'ENTREGADA',
        'EN_REVISION_INSTITUCIONAL' => 'EN REVISIÓN INSTITUCIONAL',
        'OBSERVADA' => 'OBSERVADA',
        'VALIDADA' => 'VALIDADA',
    ];
    $labels = $monthly->pluck('period')->values()->all();
    $trendCompliance = $monthly->pluck('compliance')->map(fn($v)=>(float)$v)->values()->all();
    $trendLoads = $monthly->pluck('total')->map(fn($v)=>(int)$v)->values()->all();
    $trendClosed = $monthly->pluck('closed')->map(fn($v)=>(int)$v)->values()->all();
    $unitLabels = $units->pluck('unit')->values()->all();
    $unitPct = $units->pluck('percentage')->map(fn($v)=>(float)$v)->values()->all();
    $unitTotal = $units->pluck('total')->map(fn($v)=>(int)$v)->values()->all();
    $agencyLabels = $agencies->pluck('agency')->values()->all();
    $agencyPct = $agencies->pluck('percentage')->map(fn($v)=>(float)$v)->values()->all();
    $statusLabels = $status->keys()->values()->all();
    $statusValues = $status->values()->map(fn($v)=>(int)$v)->values()->all();
    $indicatorRows = [
        ['Cumplimiento institucional','Porcentaje de cargas cerradas respecto del universo seleccionado.','Mensual',$compliance,'≥ 90%',$compliance-90],
        ['Cierre efectivo','Porcentaje de cargas que llegan a cierre validado.','Mensual',$closure,'≥ 85%',$closure-85],
        ['Presión de riesgo','Porcentaje de cargas vencidas sobre el universo.','Semanal',$risk,'≤ 10%',$risk-10],
        ['Estabilidad operativa','Relación inversa de reprogramaciones sobre el universo.','Mensual',$stability,'≥ 90%',$stability-90],
        ['Reprogramaciones','Total de cargas reprogramadas en el periodo.','Mensual',$reprogrammed,'Referencia',$reprogrammed],
    ];
@endphp

<style>
.siget-indicators{--bg:#07121e;--panel:#0c1a28;--panel2:#101f2e;--line:rgba(170,210,235,.12);--text:#f5f8fb;--muted:#8da2b5;--cyan:#22c5d7;--blue:#4d7fff;--green:#35c77a;--yellow:#e9b949;--red:#ef4655;--purple:#8b5cf6;background:radial-gradient(circle at 85% 0%,rgba(34,197,215,.08),transparent 32%),linear-gradient(180deg,#07121e,#09121b 65%,#08111a);border:1px solid var(--line);border-radius:18px;padding:18px;color:var(--text)}
.siget-indicators .hero{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:14px}.siget-indicators .eyebrow{font-size:.67rem;text-transform:uppercase;letter-spacing:.12em;color:#6edbe7;font-weight:700}.siget-indicators h2{font-size:1.45rem;color:#fff;margin:3px 0}.siget-indicators .sub{font-size:.75rem;color:var(--muted)}
.siget-indicators .filters{background:rgba(13,28,42,.92);border:1px solid var(--line);border-radius:14px;padding:14px;margin-bottom:15px}.siget-indicators label{font-size:.68rem;color:#a7bac9;margin-bottom:5px}.siget-indicators select,.siget-indicators input{background:#0a1622!important;border:1px solid rgba(170,210,235,.18)!important;color:#eaf3f8!important;border-radius:8px;font-size:.75rem}.siget-indicators .btn-clear{border:1px solid rgba(34,197,215,.28);color:#6edbe7;background:transparent}
.siget-indicators .kpi{height:100%;min-height:118px;background:linear-gradient(145deg,#101f2d,#0b1723);border:1px solid var(--line);border-radius:13px;padding:14px;position:relative;overflow:hidden}.siget-indicators .kpi:after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;background:var(--accent);opacity:.9}.siget-indicators .kpi small{color:#9db0c0;font-size:.68rem;display:block}.siget-indicators .kpi strong{font-size:1.45rem;display:block;color:#fff;margin:5px 0}.siget-indicators .delta{font-size:.65rem;color:#70d49a}.siget-indicators .progress{height:6px;background:#1b2b39;margin-top:8px}.siget-indicators .progress-bar{background:var(--accent)}
.siget-indicators .panel{background:rgba(12,26,40,.94);border:1px solid var(--line);border-radius:14px;overflow:hidden;height:100%}.siget-indicators .head{padding:13px 15px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:10px;align-items:center}.siget-indicators .head h3{font-size:.91rem;margin:0;color:#fff}.siget-indicators .head p{font-size:.65rem;color:var(--muted);margin:3px 0 0}.siget-indicators .chart{height:260px;padding:10px 12px}.siget-indicators .chart.tall{height:290px}.siget-indicators canvas{width:100%!important;height:100%!important}.siget-indicators .table{--bs-table-bg:transparent;--bs-table-color:#e8f0f5;--bs-table-border-color:var(--line);font-size:.72rem;margin:0}.siget-indicators .table th{color:#7991a5;font-size:.6rem;text-transform:uppercase;letter-spacing:.04em}.siget-indicators .table td,.siget-indicators .table th{padding:9px 10px;vertical-align:middle}.siget-indicators .pill{font-size:.58rem;padding:4px 7px;border-radius:6px;white-space:nowrap}.siget-indicators .risk-list{padding:8px 13px}.siget-indicators .risk-item{display:grid;grid-template-columns:1.2fr .7fr .7fr .8fr;gap:8px;align-items:center;padding:9px 0;border-bottom:1px solid var(--line);font-size:.68rem}.siget-indicators .risk-item:last-child{border-bottom:0}.siget-indicators .muted{color:var(--muted)}
</style>

<div class="siget-indicators">
    <div class="hero">
        <div><div class="eyebrow">SIGET · Inteligencia de indicadores</div><h2>Indicadores SIGET</h2><div class="sub">Monitoreo de desempeño, cumplimiento, riesgo, cierres y estabilidad. La vista analiza el universo seleccionado sin alterar el flujo operativo.</div></div>
        <div class="text-end"><div class="muted" style="font-size:.65rem">UNIVERSO ACTUAL</div><strong style="font-size:1.35rem">{{ number_format($total) }}</strong><div class="muted" style="font-size:.65rem">cargas</div></div>
    </div>

    <form method="GET" class="filters">
        <div class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-6"><label>Dependencia</label><select name="agency_id" class="form-select form-select-sm"><option value="">Todas las dependencias</option>@foreach(($filterAgencies ?? []) as $agency)<option value="{{ data_get($agency,'id') }}" @selected((string)($filters['agency_id']??'')===(string)data_get($agency,'id'))>{{ data_get($agency,'name') }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-6"><label>Dirección / unidad</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas las direcciones</option>@foreach(($filterUnits ?? []) as $unit)@php $ids=data_get($unit,'filter_unit_ids'); $ids=is_array($ids)?implode(',',array_map('strval',$ids)):data_get($unit,'id'); @endphp<option value="{{ $ids }}" @selected((string)($filters['organizational_unit_id']??'')===(string)$ids)>{{ data_get($unit,'name') }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-6"><label>Indicador</label><select name="indicator" class="form-select form-select-sm"><option value="cumplimiento" @selected($selectedIndicator==='cumplimiento')>Cumplimiento institucional</option><option value="cierre" @selected($selectedIndicator==='cierre')>Cierre efectivo</option><option value="riesgo" @selected($selectedIndicator==='riesgo')>Presión de riesgo</option><option value="estabilidad" @selected($selectedIndicator==='estabilidad')>Estabilidad operativa</option><option value="reprogramaciones" @selected($selectedIndicator==='reprogramaciones')>Reprogramaciones</option><option value="vencimientos" @selected($selectedIndicator==='vencimientos')>Vencimientos</option></select></div>
            <div class="col-xl-2 col-md-6"><label>Frecuencia</label><select name="frequency" class="form-select form-select-sm"><option value="diario" @selected($frequency==='diario')>Diario</option><option value="semanal" @selected($frequency==='semanal')>Semanal</option><option value="mensual" @selected($frequency==='mensual')>Mensual</option><option value="trimestral" @selected($frequency==='trimestral')>Trimestral</option></select></div>
            <div class="col-xl-1 col-md-6"><label>Estado</label><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($indicatorStatuses as $s)<option value="{{ $s }}" @selected(($filters['status']??'')===$s)>{{ $indicatorStatusLabels[$s] ?? str_replace('_',' ',$s) }}</option>@endforeach</select></div>
            <div class="col-xl-1 col-md-6"><label>Desde</label><input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-1 col-md-6"><label>Hasta</label><input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-sm"></div>
            <div class="col-xl-1 col-md-6"><button class="btn btn-primary btn-sm w-100">Aplicar</button><a href="{{ route('indicators.index') }}" class="btn btn-clear btn-sm w-100 mt-1">Limpiar</a></div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach([
            ['Cumplimiento institucional',$compliance,'%','#35c77a','vs meta institucional'],
            ['Cierre efectivo',$closure,'%','#22c5d7','cargas cerradas'],
            ['Presión de riesgo',$risk,'%','#ef4655','menor es mejor'],
            ['Vencimientos próximos',$k['due_soon']??0,'','#ef7b32','próximas 72 horas'],
            ['Reprogramaciones',$reprogrammed,'','#8b5cf6','presión de cambio'],
            ['Cargas activas',$active,'','#8d9aa6','universo operativo']
        ] as $card)
        <div class="col-6 col-xl-2"><div class="kpi" style="--accent:{{ $card[3] }}"><small>{{ $card[0] }}</small><strong>{{ $card[1] }}{{ $card[2] }}</strong><div class="delta">{{ $card[4] }}</div><div class="progress"><div class="progress-bar" style="width:{{ is_numeric($card[1]) && (float)$card[1] <= 100 ? max(0,min(100,(float)$card[1])) : 35 }}%"></div></div></div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5"><div class="panel"><div class="head"><div><h3>Evolución del indicador seleccionado</h3><p>{{ ucfirst($selectedIndicator) }} · {{ ucfirst($frequency) }} · comportamiento frente al periodo</p></div><span class="pill bg-info-subtle text-info">{{ ucfirst($frequency) }}</span></div><div class="chart tall"><canvas id="sigetIndicatorTrend"></canvas></div></div></div>
        <div class="col-xl-4"><div class="panel"><div class="head"><div><h3>Cumplimiento por Dirección / Unidad</h3><p>Comparativo de desempeño del universo seleccionado.</p></div></div><div class="chart tall"><canvas id="sigetIndicatorUnits"></canvas></div></div></div>
        <div class="col-xl-3"><div class="panel"><div class="head"><div><h3>Distribución por Dependencia</h3><p>Indicador seleccionado por dependencia.</p></div></div><div class="chart tall"><canvas id="sigetIndicatorAgencies"></canvas></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4"><div class="panel"><div class="head"><div><h3>Comparativo del indicador por periodo</h3><p>Periodo actual contra comportamiento histórico.</p></div></div><div class="chart"><canvas id="sigetIndicatorCompare"></canvas></div></div></div>
        <div class="col-xl-4"><div class="panel"><div class="head"><div><h3>Indicadores con mayor riesgo</h3><p>Brecha frente a la referencia de lectura ejecutiva.</p></div></div><div class="risk-list">@foreach($indicatorRows as $row)@php $value=(float)$row[3]; $gap=(float)$row[5]; $bad=($row[0]==='Presión de riesgo') ? $gap>0 : ($row[0]==='Reprogramaciones' ? $value>0 : $gap<0); $label=$bad?'ATENCIÓN':'SEGUIMIENTO'; @endphp<div class="risk-item"><span>{{ $row[0] }}</span><strong>{{ is_numeric($row[3]) ? $row[3].($row[0]==='Reprogramaciones'?'':'%') : $row[3] }}</strong><span class="muted">{{ $row[4] }}</span><span class="pill {{ $bad?'bg-danger':'bg-warning text-dark' }}">{{ $label }}</span></div>@endforeach</div></div></div>
        <div class="col-xl-4"><div class="panel"><div class="head"><div><h3>Comportamiento por Dirección</h3><p>Lectura comparativa del indicador seleccionado.</p></div></div><div class="chart"><canvas id="sigetIndicatorRadar"></canvas></div></div></div>
    </div>

    <div class="panel"><div class="head"><div><h3>Detalle de indicadores</h3><p>Definición, frecuencia, valor actual, referencia, brecha y estado para decisión.</p></div><span class="pill bg-secondary">{{ count($indicatorRows) }} indicadores</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Indicador</th><th>Descripción</th><th>Frecuencia</th><th>Valor actual</th><th>Meta / referencia</th><th>Brecha</th><th>Tendencia</th><th>Estado</th><th>Acción</th></tr></thead><tbody>@foreach($indicatorRows as $row)@php $gap=(float)$row[5]; $bad=($row[0]==='Presión de riesgo')?$gap>0:($row[0]==='Reprogramaciones'?$row[3]>0:$gap<0); @endphp<tr><td><strong>{{ $row[0] }}</strong></td><td class="muted">{{ $row[1] }}</td><td>{{ $row[2] }}</td><td><strong>{{ $row[3] }}{{ $row[0]==='Reprogramaciones'?'':'%' }}</strong></td><td>{{ $row[4] }}</td><td class="{{ $bad?'text-danger':'text-success' }}">{{ $row[0]==='Reprogramaciones' ? $row[5] : number_format($gap,1).' pp' }}</td><td><span class="{{ $bad?'text-danger':'text-success' }}">{{ $bad?'↘':'↗' }}</span></td><td><span class="pill {{ $bad?'bg-danger':'bg-success' }}">{{ $bad?'Atención':'Seguimiento' }}</span></td><td><a class="btn btn-outline-info btn-sm" href="{{ route('dashboard', array_filter(['agency_id'=>$filters['agency_id']??null,'organizational_unit_id'=>$filters['organizational_unit_id']??null,'status'=>$filters['status']??null,'from'=>$filters['from']??null,'to'=>$filters['to']??null])) }}">Ver detalle</a></td></tr>@endforeach</tbody></table></div></div>
</div>

<script type="application/json" data-siget-chart="sigetIndicatorTrend">{!! json_encode(['type'=>'line','data'=>['labels'=>$labels,'datasets'=>[['label'=>'Indicador %','data'=>$trendCompliance,'tension'=>.35],['label'=>'Cierres','data'=>$trendClosed,'tension'=>.35],['label'=>'Cargas','data'=>$trendLoads,'tension'=>.35]]],'options'=>['plugins'=>['legend'=>['position'=>'bottom']],'maintainAspectRatio'=>false]]) !!}</script>
<script type="application/json" data-siget-chart="sigetIndicatorUnits">{!! json_encode(['type'=>'bar','data'=>['labels'=>$unitLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$unitPct],['label'=>'Cargas','data'=>$unitTotal]]],'options'=>['indexAxis'=>'y','plugins'=>['legend'=>['position'=>'bottom']],'maintainAspectRatio'=>false]]) !!}</script>
<script type="application/json" data-siget-chart="sigetIndicatorAgencies">{!! json_encode(['type'=>'doughnut','data'=>['labels'=>$agencyLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$agencyPct]]],'options'=>['plugins'=>['legend'=>['position'=>'bottom']],'maintainAspectRatio'=>false]]) !!}</script>
<script type="application/json" data-siget-chart="sigetIndicatorCompare">{!! json_encode(['type'=>'bar','data'=>['labels'=>['Cumplimiento','Cierre efectivo','Presión de riesgo','Estabilidad'], 'datasets'=>[['label'=>'Periodo actual','data'=>[$compliance,$closure,$risk,$stability]],['label'=>'Referencia','data'=>[90,85,10,90]]]],'options'=>['plugins'=>['legend'=>['position'=>'bottom']],'maintainAspectRatio'=>false]]) !!}</script>
<script type="application/json" data-siget-chart="sigetIndicatorRadar">{!! json_encode(['type'=>'radar','data'=>['labels'=>$unitLabels,'datasets'=>[['label'=>'Cumplimiento %','data'=>$unitPct]]],'options'=>['scales'=>['r'=>['beginAtZero'=>true,'max'=>100]],'plugins'=>['legend'=>['position'=>'bottom']],'maintainAspectRatio'=>false]]) !!}</script>
@endsection
