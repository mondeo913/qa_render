<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page{margin:28px 32px}
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#243247;margin:0;background:#fff}
h1,h2,h3,p{margin:0}h1{font-size:20px;color:#10213b;letter-spacing:.2px}h2{font-size:13px;color:#10213b;margin:18px 0 7px}h3{font-size:10px;color:#10213b}
.header{border:1px solid #cfd7e3;border-radius:7px;background:#10213b;color:#fff;padding:15px 16px;margin-bottom:12px}.header .eyebrow{font-size:7px;text-transform:uppercase;letter-spacing:1px;color:#b9c8dc;font-weight:bold;margin-bottom:4px}.header p{font-size:8px;color:#d8e1ec;margin-top:3px}.meta{width:100%;border-collapse:collapse;margin-top:10px}.meta td{padding:6px 7px;border:1px solid rgba(255,255,255,.18);font-size:7.5px}.meta b{display:block;color:#8ea7c5;font-size:6.5px;text-transform:uppercase;margin-bottom:2px}
.kpis{width:100%;border-collapse:separate;border-spacing:6px 0;margin-bottom:12px}.kpis td{width:16.66%;border:1px solid #d6dde7;background:#f7f9fb;border-top:3px solid #10213b;padding:8px 6px;text-align:center}.kpis .ok{border-top-color:#20a865}.kpis .warn{border-top-color:#db9b17}.kpis .danger{border-top-color:#d94a4a}.kpis .label{font-size:6.5px;text-transform:uppercase;color:#6b788b}.kpis strong{display:block;font-size:16px;color:#10213b;margin-top:2px}
.section{border:1px solid #d8dee7;border-radius:6px;overflow:hidden;margin-bottom:12px}.section-title{background:#10213b;color:#fff;padding:8px 10px;font-size:9px;font-weight:bold}.section-sub{padding:6px 10px;background:#f0f3f7;color:#5f6d80;font-size:7px}
.cards{width:100%;border-collapse:separate;border-spacing:6px}.cards td{border:1px solid #d8dee7;padding:7px 8px;background:#fff}.status-ok{border-left:4px solid #20a865!important}.status-warn{border-left:4px solid #db9b17!important}.status-danger{border-left:4px solid #d94a4a!important}.status-info{border-left:4px solid #2e79b9!important}.cards .n{font-size:14px;font-weight:bold;color:#10213b}.cards .label{font-size:7px;font-weight:bold;color:#425269}.cards .note{font-size:6.5px;color:#798598;margin-top:2px}
.dot{font-size:7px}.green{color:#16814f}.gold{color:#9b6a00}.red{color:#a82c2c}.blue{color:#1f69a3}
.table{width:100%;border-collapse:collapse;font-size:7.3px}.table th{background:#15263f;color:#fff;text-align:left;padding:6px;font-size:6.5px;text-transform:uppercase}.table td{padding:6px;border:1px solid #e0e5ec}.table tr:nth-child(even) td{background:#fbfcfd}.right{text-align:right}.center{text-align:center}
.badge{display:inline-block;padding:3px 5px;border-radius:8px;font-size:6px;font-weight:bold}.b-ok{background:#e7f6ee;color:#127a49}.b-warn{background:#fff4d7;color:#966700}.b-danger{background:#fdeaea;color:#a82d2d}.b-info{background:#e9f4fc;color:#1f679e}
.group{border:1px solid #cfd7e2;border-radius:6px;overflow:hidden;margin-bottom:10px}.group-head{background:#e9eef4;padding:7px 9px;color:#10213b;font-weight:bold;font-size:8px}.group-sub{background:#f7f9fb;padding:6px 9px;color:#3e4d61;font-weight:bold;font-size:7px;border-top:1px solid #dbe1e9;border-bottom:1px solid #dbe1e9}.mini-total{float:right;color:#69778a;font-weight:normal;font-size:6.5px}
.bar-wrap{margin:5px 0}.bar-line{width:100%;height:8px;background:#edf1f5;border:1px solid #d9e0e8}.bar-fill{height:8px;background:#2e79b9}.bar-fill.ok{background:#20a865}.bar-fill.warn{background:#db9b17}.bar-fill.danger{background:#d94a4a}.bar-label{font-size:6.8px;color:#44536a;margin-bottom:2px}.bar-value{float:right;font-weight:bold;color:#10213b}
.footer{margin-top:16px;border-top:1px solid #d7dee8;padding-top:6px;color:#758194;font-size:6.5px}.page-break{page-break-before:always}
</style>
</head>
<body>
@php
    $k = $analytics['kpis'] ?? [];
    $status = collect($analytics['status_distribution'] ?? []);
    $agencies = collect($analytics['agency_performance'] ?? []);
    $units = collect($analytics['unit_performance'] ?? []);
    $monthly = collect($analytics['monthly_trend'] ?? []);
    $allowed = [
        'PROGRAMADA' => ['label'=>'PROGRAMADO','class'=>'status-info','badge'=>'b-info','note'=>'En seguimiento'],
        'REPROGRAMADA' => ['label'=>'REPROGRAMADO','class'=>'status-warn','badge'=>'b-warn','note'=>'Requiere seguimiento'],
        'VALIDADO_Y_CERRADO' => ['label'=>'VALIDADO Y CERRADO','class'=>'status-ok','badge'=>'b-ok','note'=>'Cumplimiento confirmado'],
        'VENCIDA' => ['label'=>'VENCIDO','class'=>'status-danger','badge'=>'b-danger','note'=>'Incumplimiento'],
    ];
    $maxAgency = max(1, (int)$agencies->max(fn($r)=>(int)($r['total']??0)));
    $maxUnit = max(1, (int)$units->max(fn($r)=>(int)($r['total']??0)));
@endphp

<div class="header">
    <div class="eyebrow">SIGET · Centro de Reportes · Formato Crystal</div>
    <h1 style="color:#fff">Reporte Ejecutivo Institucional</h1>
    <p>Resumen ejecutivo y lectura jerárquica por Dependencia → Dirección / Unidad → desempeño.</p>
    <table class="meta"><tr>
        <td><b>Periodo</b>{{ ($analytics['period_min'] ?? null) ?: 'Universo actual' }} @if(!empty($analytics['period_max'])) → {{ $analytics['period_max'] }} @endif</td>
        <td><b>Generado por</b>{{ $generatedBy->name }}</td>
        <td><b>Fecha de emisión</b>{{ now()->format('d/m/Y H:i') }}</td>
        <td><b>Universo</b>{{ number_format((int)($k['total']??0)) }} cargas</td>
    </tr></table>
</div>

<table class="kpis"><tr>
    <td><div class="label">Total</div><strong>{{ number_format((int)($k['total']??0)) }}</strong></td>
    <td class="warn"><div class="label">Activas</div><strong>{{ number_format((int)($k['active']??0)) }}</strong></td>
    <td class="ok"><div class="label">Cerradas</div><strong>{{ number_format((int)($k['closed']??0)) }}</strong></td>
    <td class="danger"><div class="label">Vencidas</div><strong>{{ number_format((int)($k['overdue']??0)) }}</strong></td>
    <td class="warn"><div class="label">Reprogramadas</div><strong>{{ number_format((int)($k['reprogrammed']??0)) }}</strong></td>
    <td><div class="label">Cumplimiento</div><strong>{{ number_format((float)($k['compliance']??0),1) }}%</strong></td>
</tr></table>

<div class="section">
    <div class="section-title">1 · Lectura ejecutiva por estado</div>
    <div class="section-sub">Se muestran únicamente los estados oficiales de lectura ejecutiva.</div>
    <table class="cards"><tr>
    @foreach($allowed as $code=>$meta)
        @php($n=(int)($status[$code]??0))
        <td class="{{ $meta['class'] }}"><div><span class="dot">●</span> <span class="label">{{ $meta['label'] }}</span></div><div class="n">{{ $n }}</div><div class="note">{{ $meta['note'] }}</div></td>
    @endforeach
    </tr></table>
</div>

<div class="section">
    <div class="section-title">2 · Cumplimiento por Dependencia</div>
    <div class="section-sub">Cada dependencia se presenta como bloque independiente con subtotal y semáforo.</div>
    <table class="table"><thead><tr><th>Dependencia</th><th class="center">Cargas</th><th class="center">Cerradas</th><th class="center">Vencidas</th><th class="center">Cumplimiento</th><th>Semáforo</th></tr></thead><tbody>
    @forelse($agencies as $row)
        @php($pct=(float)($row['percentage']??0)) @php($late=(int)($row['overdue']??0)) @php($cls=$late>0?'b-danger':($pct>=90?'b-ok':'b-warn')) @php($label=$late>0?'INCUMPLIMIENTO':($pct>=90?'EN CUMPLIMIENTO':'ATENCIÓN'))
        <tr><td><strong>{{ $row['agency']??'Sin dependencia' }}</strong></td><td class="center">{{ $row['total']??0 }}</td><td class="center">{{ $row['closed']??0 }}</td><td class="center">{{ $late }}</td><td class="center"><strong>{{ $pct }}%</strong></td><td><span class="badge {{ $cls }}">{{ $label }}</span></td></tr>
    @empty<tr><td colspan="6" class="center">Sin datos para el universo seleccionado.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="section">
    <div class="section-title">3 · Desempeño por Dirección / Unidad</div>
    <table class="table"><thead><tr><th>Dirección / Unidad</th><th class="center">Entregables</th><th class="center">Validados</th><th class="center">Cumplimiento</th><th style="width:32%">Volumen</th></tr></thead><tbody>
    @forelse($units as $row)
        @php($n=(int)($row['total']??0)) @php($pct=(float)($row['percentage']??0)) @php($bar=$maxUnit ? min(100,round(100*$n/$maxUnit)) : 0)
        <tr><td><strong>{{ $row['unit']??'Sin unidad' }}</strong></td><td class="center">{{ $n }}</td><td class="center">{{ $row['validated']??0 }}</td><td class="center">{{ $pct }}%</td><td><div class="bar-label">Carga relativa <span class="bar-value">{{ $n }}</span></div><div class="bar-line"><div class="bar-fill {{ $pct>=90?'ok':($pct>=60?'warn':'danger') }}" style="width:{{ $bar }}%"></div></div></td></tr>
    @empty<tr><td colspan="5" class="center">Sin datos para el universo seleccionado.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="section">
    <div class="section-title">4 · Tendencia mensual</div>
    <div class="section-sub">Entradas, cierres y cumplimiento del periodo contratado.</div>
    <table class="table"><thead><tr><th>Periodo</th><th class="center">Cargas</th><th class="center">Cerradas</th><th class="center">Cumplimiento</th><th style="width:42%">Nivel de cumplimiento</th></tr></thead><tbody>
    @forelse($monthly as $row)
        @php($pct=(float)($row['compliance']??0))
        <tr><td><strong>{{ $row['period']??'—' }}</strong></td><td class="center">{{ $row['total']??0 }}</td><td class="center">{{ $row['closed']??0 }}</td><td class="center">{{ $pct }}%</td><td><div class="bar-line"><div class="bar-fill {{ $pct>=90?'ok':($pct>=60?'warn':'danger') }}" style="width:{{ min(100,$pct) }}%"></div></div></td></tr>
    @empty<tr><td colspan="5" class="center">Sin información mensual.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="page-break"></div>
<h2>5 · Lectura por Dependencia y Dirección</h2>
<p style="font-size:7px;color:#66758a;margin-bottom:8px">La agrupación está diseñada para lectura ejecutiva y revisión documental posterior.</p>
@forelse($agencies as $agencyRow)
    @php($agencyName=$agencyRow['agency']??'Sin dependencia')
    <div class="group">
        <div class="group-head">DEPENDENCIA · {{ $agencyName }} <span class="mini-total">{{ (int)($agencyRow['total']??0) }} cargas</span></div>
        @php($unitRows=$units->filter(fn($u)=>str_contains(mb_strtolower($u['unit']??''), mb_strtolower($agencyName))))
        @if($unitRows->isEmpty())
            <div class="group-sub">Direcciones / unidades conforme al universo analítico seleccionado</div>
            <table class="table"><tbody><tr><td>La dependencia se encuentra resumida en el cuadro de desempeño; el detalle de órdenes y pautas se encuentra disponible en el reporte web.</td></tr></tbody></table>
        @else
            @foreach($unitRows as $unitRow)
                <div class="group-sub">Dirección / Unidad · {{ $unitRow['unit']??'Sin unidad' }} <span class="mini-total">{{ (int)($unitRow['total']??0) }} entregables</span></div>
                <table class="table"><tbody><tr><td>Entregables</td><td class="center">{{ $unitRow['total']??0 }}</td><td>Validados</td><td class="center">{{ $unitRow['validated']??0 }}</td><td>Cumplimiento</td><td class="center">{{ $unitRow['percentage']??0 }}%</td></tr></tbody></table>
            @endforeach
        @endif
    </div>
@empty
    <div class="section"><div class="section-sub">Sin dependencias para el universo seleccionado.</div></div>
@endforelse

<div class="footer">SIGET · Reporte generado con el universo y alcance autorizado del usuario. Formato optimizado para impresión y archivo.</div>
</body>
</html>
