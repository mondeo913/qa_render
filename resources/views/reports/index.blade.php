@extends('layouts.app')
@section('title','Centro de Reportes SIGET')
@section('page-title','Centro de Reportes SIGET')
@section('page-subtitle','Visor institucional de reportes, evidencias y trazabilidad')
@section('content')
@php
    $report = request('report', 'executive');
    $summary = $evidenceSummary ?? ['expected'=>0,'received'=>0,'validated'=>0,'pending'=>0,'observed'=>0,'review'=>0,'delivery_percentage'=>0,'validation_percentage'=>0];
    $statusLabels = ['PROGRAMADA'=>'PROGRAMADO','REPROGRAMADA'=>'REPROGRAMADO','VALIDADO_Y_CERRADO'=>'VALIDADO Y CERRADO','VENCIDA'=>'VENCIDO'];
    $reportLinks = [
        'executive' => 'Reporte ejecutivo institucional',
        'compliance' => 'Cumplimiento por dependencia',
        'pending' => 'Seguimiento de pendientes',
        'evidence' => 'Detalle de evidencias',
        'audit' => 'Auditoría y trazabilidad',
    ];
    $linkFor = fn ($type) => route('reports.index', array_merge(request()->query(), ['report' => $type]));
@endphp
<style>
.report-shell{background:var(--bg);color:var(--text);min-height:100%;font-size:.78rem}
.report-hero{background:linear-gradient(135deg,#10213b,#174e68);color:#fff;padding:22px 24px;border-radius:14px;margin-bottom:14px}
.report-hero h2{margin:0;font-weight:800;color:#fff}.report-hero p{margin:5px 0 0;color:#d5e4eb}.report-eyebrow{text-transform:uppercase;letter-spacing:.11em;font-size:.62rem;color:#8be0e3;margin-bottom:4px}
.report-box{background:var(--surface);border:1px solid var(--border);border-radius:12px}.report-toolbar{padding:14px;margin-bottom:14px}.report-toolbar label{display:block;font-size:.62rem;font-weight:800;margin-bottom:4px}.report-actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.report-actions .btn{font-size:.7rem}.report-nav{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}.report-nav a{padding:8px 11px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);font-weight:700;font-size:.69rem;text-decoration:none}.report-nav a.active{background:#0d7f8c;color:#fff;border-color:#0d7f8c}
.report-kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:9px;margin-bottom:14px}.report-kpi{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px}.report-kpi small{display:block;color:var(--muted);font-size:.58rem;text-transform:uppercase;letter-spacing:.04em}.report-kpi strong{display:block;font-size:1.35rem;margin-top:3px}.report-kpi em{font-style:normal;color:var(--muted);font-size:.59rem}.report-kpi.green{border-left:4px solid #1fa463}.report-kpi.amber{border-left:4px solid #d98a00}.report-kpi.red{border-left:4px solid #d64550}
.report-preview{padding:18px;margin-top:14px}.report-paper{background:#fff;color:#243247;border:1px solid #cfd7e2;border-radius:4px;padding:18px;box-shadow:0 2px 7px rgba(15,35,55,.08)}
.paper-head{background:#10213b;color:#fff;padding:12px 14px;border-radius:5px;margin-bottom:10px}.paper-head h3{margin:0;color:#fff;font-size:1rem}.paper-head p{margin:3px 0 0;color:#d8e1ec;font-size:.67rem}.paper-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin-top:9px}.paper-meta div{border:1px solid #52637b;padding:6px;font-size:.62rem}.paper-meta b{display:block;color:#b9c8dc;font-size:.54rem;text-transform:uppercase}
.report-section{border:1px solid #d8dee7;border-radius:4px;overflow:hidden;margin-top:10px}.report-section-title{background:#10213b;color:#fff;padding:7px 9px;font-size:.72rem;font-weight:800}.report-subtitle{background:#f0f3f7;color:#5f6d80;padding:6px 9px;font-size:.62rem}.report-table{width:100%;border-collapse:collapse;font-size:.65rem}.report-table th{background:#15263f;color:#fff;padding:7px;text-align:left;font-size:.57rem;text-transform:uppercase}.report-table td{padding:7px;border:1px solid #e0e5ec}.report-table tr:nth-child(even) td{background:#f7f9fb}.report-table .num{text-align:right;font-variant-numeric:tabular-nums}.badge{display:inline-block;padding:3px 6px;border-radius:5px;font-size:.56rem;font-weight:800}.badge.ok{background:#e7f6ee;color:#127a49}.badge.warn{background:#fff4d7;color:#966700}.badge.danger{background:#fdeaea;color:#a82d2d}.badge.info{background:#e8f4f7;color:#0d6673}
.report-total{display:grid;grid-template-columns:repeat(6,1fr);gap:7px;background:#f0f3f7;border:1px solid #d8dee7;padding:10px;margin-top:10px}.report-total strong{display:block;font-size:1rem;color:#10213b}.report-total span{font-size:.57rem;color:#69778a;text-transform:uppercase}.empty{padding:22px;text-align:center;color:var(--muted)}
@media(max-width:1100px){.report-kpis{grid-template-columns:repeat(3,1fr)}.paper-meta{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.report-kpis{grid-template-columns:repeat(2,1fr)}.report-total{grid-template-columns:repeat(2,1fr)}.report-table{font-size:.58rem}}
</style>
<div class="report-shell">
    <div class="report-hero">
        <div class="report-eyebrow">SIGET · Centro de reportes · Formato institucional</div>
        <h2>{{ $reportLinks[$report] ?? 'Centro de reportes' }}</h2>
        <p>Consulta, previsualiza y exporta información agrupada por dependencia, dirección, unidad, campaña y evidencia.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="report-box report-toolbar">
        <input type="hidden" name="report" value="{{ $report }}">
        <div class="row g-3 align-items-end">
            <div class="col-lg-2"><label>Dependencia</label><select name="agency_id" class="form-select form-select-sm"><option value="">Todas las autorizadas</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(($filters['agency_id'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label>Dirección / Unidad</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas las autorizadas</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(($filters['organizational_unit_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Estado ejecutivo</label><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($statuses as $code=>$label)<option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $statusLabels[$code] ?? $label }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Desde</label><input type="month" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-2"><label>Hasta</label><input type="month" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-1"><button class="btn btn-primary btn-sm w-100">Aplicar</button></div>
        </div>
        <div class="report-actions">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('reports.index') }}">Limpiar</a>
            @if($canExport)
                <a class="btn btn-primary btn-sm" href="{{ route('reports.pdf', request()->query()) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Generar PDF</a>
                <a class="btn btn-success btn-sm" href="{{ route('reports.xlsx', request()->query()) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>
                <a class="btn btn-outline-success btn-sm" href="{{ route('reports.csv', request()->query()) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Imprimir</button>
            @endif
        </div>
    </form>

    <nav class="report-nav" aria-label="Plantillas de reporte">
        @foreach($reportLinks as $type=>$label)
            <a href="{{ $linkFor($type) }}" class="{{ $report === $type ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
        @if($canBuildReports)<a href="{{ route('admin.settings') }}">Plantillas de reportes</a>@endif
    </nav>

    @if($report === 'executive')
        <div class="report-kpis">
            <div class="report-kpi"><small>Evidencias esperadas</small><strong>{{ number_format($summary['expected']) }}</strong><em>obligación del periodo</em></div>
            <div class="report-kpi green"><small>Evidencias recibidas</small><strong>{{ number_format($summary['received']) }}</strong><em>{{ $summary['delivery_percentage'] }}% de entrega</em></div>
            <div class="report-kpi green"><small>Evidencias validadas</small><strong>{{ number_format($summary['validated']) }}</strong><em>{{ $summary['validation_percentage'] }}% del universo</em></div>
            <div class="report-kpi amber"><small>Evidencias pendientes</small><strong>{{ number_format($summary['pending']) }}</strong><em>requieren carga</em></div>
            <div class="report-kpi amber"><small>En revisión</small><strong>{{ number_format($summary['review']) }}</strong><em>pendientes de decisión</em></div>
            <div class="report-kpi red"><small>Observadas o rechazadas</small><strong>{{ number_format($summary['observed']) }}</strong><em>requieren corrección</em></div>
        </div>
        <div class="report-box report-preview">
            <div class="report-paper">
                <div class="paper-head"><h3>Reporte Ejecutivo Institucional</h3><p>Resumen de cumplimiento, riesgos y desempeño por dependencia.</p><div class="paper-meta"><div><b>Periodo</b>{{ ($filters['from'] ?? '') ?: 'Universo actual' }} @if(!empty($filters['to'])) → {{ $filters['to'] }} @endif</div><div><b>Generado por</b>{{ auth()->user()->name }}</div><div><b>Emisión</b>{{ now()->format('d/m/Y H:i') }}</div><div><b>Registros</b>{{ number_format($loads->count()) }} cargas</div></div></div>
                <div class="report-section"><div class="report-section-title">Resumen institucional de evidencias</div><table class="report-table"><thead><tr><th>Indicador</th><th class="num">Esperadas</th><th class="num">Recibidas</th><th class="num">Validadas</th><th class="num">Pendientes</th><th class="num">Observadas</th><th class="num">%</th></tr></thead><tbody><tr><td><strong>Total del universo filtrado</strong></td><td class="num">{{ number_format($summary['expected']) }}</td><td class="num">{{ number_format($summary['received']) }}</td><td class="num">{{ number_format($summary['validated']) }}</td><td class="num">{{ number_format($summary['pending']) }}</td><td class="num">{{ number_format($summary['observed']) }}</td><td class="num"><span class="badge {{ $summary['delivery_percentage'] >= 80 ? 'ok' : ($summary['delivery_percentage'] >= 50 ? 'warn' : 'danger') }}">{{ $summary['delivery_percentage'] }}%</span></td></tr></tbody></table></div>
                <div class="report-section"><div class="report-section-title">Focos de atención</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Campaña / carga</th><th>Fecha límite</th><th>Estado</th><th>Riesgo</th></tr></thead><tbody>@forelse($reportRows->filter(fn($r)=>$r['pending']>0 || $r['observed']>0 || in_array($r['status'],['VENCIDA','REPROGRAMADA'],true))->take(8) as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['close'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td><span class="badge {{ $row['risk']==='ALTO' ? 'danger' : 'warn' }}">{{ $row['risk'] }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No hay focos de atención en el universo actual.</td></tr>@endforelse</tbody></table></div>
                <div class="report-total"><div><span>Esperadas</span><strong>{{ number_format($summary['expected']) }}</strong></div><div><span>Recibidas</span><strong>{{ number_format($summary['received']) }}</strong></div><div><span>Validadas</span><strong>{{ number_format($summary['validated']) }}</strong></div><div><span>Pendientes</span><strong>{{ number_format($summary['pending']) }}</strong></div><div><span>Observadas</span><strong>{{ number_format($summary['observed']) }}</strong></div><div><span>Entrega</span><strong>{{ $summary['delivery_percentage'] }}%</strong></div></div>
            </div>
        </div>
    @elseif($report === 'compliance')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Cumplimiento por dependencia, dirección y unidad</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Campaña / carga</th><th class="num">Esperadas</th><th class="num">Recibidas</th><th class="num">Validadas</th><th class="num">Pendientes</th><th class="num">Cumplimiento</th></tr></thead><tbody>@forelse($reportRows as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['title'] }}</td><td class="num">{{ $row['expected'] }}</td><td class="num">{{ $row['received'] }}</td><td class="num">{{ $row['validated'] }}</td><td class="num">{{ $row['pending'] }}</td><td class="num"><span class="badge {{ $row['expected'] && $row['received']/$row['expected'] >= .8 ? 'ok' : ($row['expected'] && $row['received']/$row['expected'] >= .5 ? 'warn' : 'danger') }}">{{ $row['expected'] ? round($row['received']*100/$row['expected'],1) : 0 }}%</span></td></tr>@empty<tr><td colspan="8" class="empty">No hay registros para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @elseif($report === 'pending')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Seguimiento de pendientes y vencimientos</div><div class="report-subtitle">Solo se muestran cargas con evidencias pendientes, observadas, rechazadas o con riesgo operativo.</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Campaña / carga</th><th>Responsable</th><th>Fecha límite</th><th class="num">Pendientes</th><th class="num">Observadas</th><th>Estado</th><th>Riesgo</th></tr></thead><tbody>@forelse($reportRows->filter(fn($r)=>$r['pending']>0 || $r['observed']>0 || in_array($r['status'],['VENCIDA','REPROGRAMADA'],true)) as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['responsible'] }}</td><td>{{ $row['close'] }}</td><td class="num">{{ $row['pending'] }}</td><td class="num">{{ $row['observed'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td><span class="badge {{ $row['risk']==='ALTO' ? 'danger' : 'warn' }}">{{ $row['risk'] }}</span></td></tr>@empty<tr><td colspan="9" class="empty">No hay pendientes para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @elseif($report === 'evidence')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Detalle de evidencias y expedientes</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Campaña / carga</th><th>Responsable</th><th>Apertura</th><th>Cierre</th><th>Estado</th><th class="num">Esperadas</th><th class="num">Recibidas</th><th class="num">Validadas</th></tr></thead><tbody>@forelse($reportRows as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['responsible'] }}</td><td>{{ $row['open'] }}</td><td>{{ $row['close'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td class="num">{{ $row['expected'] }}</td><td class="num">{{ $row['received'] }}</td><td class="num">{{ $row['validated'] }}</td></tr>@empty<tr><td colspan="10" class="empty">No hay evidencias para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @else
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Historial de cambios de estado</div><table class="report-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Dependencia</th><th>Carga</th><th>Estado anterior</th><th>Estado nuevo</th><th>Motivo</th></tr></thead><tbody>@forelse($statusHistory as $item)<tr><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td><td>{{ $item->user?->name ?? 'Sistema' }}</td><td>{{ $item->scheduledLoad?->agency?->name ?? '—' }}</td><td>{{ $item->scheduledLoad?->title ?? '—' }}</td><td>{{ $statusLabels[$item->old_status] ?? $item->old_status ?? '—' }}</td><td>{{ $statusLabels[$item->new_status] ?? $item->new_status }}</td><td>{{ $item->reason ?: '—' }}</td></tr>@empty<tr><td colspan="7" class="empty">No hay cambios de estado en el universo seleccionado.</td></tr>@endforelse</tbody></table></div><div class="report-section"><div class="report-section-title">Eventos de auditoría</div><table class="report-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Entidad</th><th>ID</th></tr></thead><tbody>@forelse($auditRows as $item)<tr><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td><td>{{ $item->user?->name ?? 'Sistema' }}</td><td>{{ $item->event }}</td><td>{{ class_basename($item->entity_type) }}</td><td>{{ $item->entity_id ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="empty">No hay eventos de auditoría en el universo seleccionado.</td></tr>@endforelse</tbody></table></div></div>
    @endif
</div>
@endsection
