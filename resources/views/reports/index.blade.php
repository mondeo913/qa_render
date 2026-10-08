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
    if ($canBuildReports) {
        $reportLinks['builder'] = 'Plantillas de reportes';
    }
    $linkFor = fn ($type) => route('reports.index', array_merge(request()->query(), ['report' => $type]));
@endphp
<div class="report-shell">
    <div class="report-hero">
        <div class="report-eyebrow">SIGET · Centro de reportes · Formato institucional</div>
        <h2>{{ $reportLinks[$report] ?? 'Centro de reportes' }}</h2>
        <p>Consulta, previsualiza y exporta información agrupada por dependencia, Dirección, Pauta (Excel), carga y evidencia dentro de tu alcance autorizado.</p>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="report-box report-toolbar">
        <input type="hidden" name="report" value="{{ $report }}">
        <div class="row g-3 align-items-end"><div class="col-lg-2"><label>Pauta (Excel)</label><select name="pauta_id" class="form-select form-select-sm"><option value="">Todas las pautas visibles</option>@foreach(($pautas ?? []) as $pauta)<option value="{{ $pauta['id'] }}" @selected((string)($filters['pauta_id'] ?? '') === (string)$pauta['id'])>{{ $pauta['name'] }} · {{ $pauta['agency'] }}</option>@endforeach</select></div>
            
            <div class="col-lg-2"><label>Dependencia</label><select name="agency_id" class="form-select form-select-sm"><option value="">Todas las autorizadas</option>@foreach($agencies as $a)<option value="{{ $a->id }}" @selected(($filters['agency_id'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label>Dirección / Unidad</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Todas las autorizadas</option>@foreach($units as $u)<option value="{{ $u->id }}" @selected(($filters['organizational_unit_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Estado ejecutivo</label><select name="status" class="form-select form-select-sm"><option value="">Todos</option>@foreach($statuses as $code=>$label)<option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $statusLabels[$code] ?? $label }}</option>@endforeach</select></div>
            <div class="col-lg-2"><label>Desde</label><input type="month" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-2"><label>Hasta</label><input type="month" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-lg-1"><button class="btn btn-primary btn-sm w-100">Aplicar</button></div>
        </div>
        <div class="report-period-segmented mt-2">
            <strong class="small">Rango segmentado:</strong>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-report-range="today">Hoy</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-report-range="week">Semana actual</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-report-range="month">Mes actual</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-report-range="quarter">Trimestre actual</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-report-range="custom">Personalizado</button>
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
    </nav>

    @if($report === 'builder' && $canBuildReports)
        <div class="report-box report-preview">
            <div class="report-section" style="margin-top:0"><div class="report-section-title">Configurador de plantillas de reportes</div><div class="report-subtitle">Selecciona opciones; no necesitas escribir nombres técnicos de campos. La vista previa se actualiza con las casillas marcadas.</div></div>
            <div class="preset-buttons"><button type="button" class="btn btn-outline-secondary btn-sm" data-preset="executive">Usar plantilla ejecutiva</button><button type="button" class="btn btn-outline-secondary btn-sm" data-preset="pending">Usar plantilla de pendientes</button><button type="button" class="btn btn-outline-secondary btn-sm" data-preset="audit">Usar plantilla de auditoría</button></div>
            <div class="builder-grid">
                <div class="report-box builder-panel">
                    <h3>1. Tipo y presentación</h3>
                    <label class="form-label small fw-bold">Tipo de reporte</label><select id="builderReportType" class="form-select form-select-sm mb-3"><option value="executive">Reporte ejecutivo institucional</option><option value="compliance">Cumplimiento por dependencia</option><option value="pending">Seguimiento de pendientes</option><option value="evidence">Detalle de evidencias</option><option value="audit">Auditoría y trazabilidad</option></select>
                    <label class="form-label small fw-bold">Agrupar resultados por</label><select id="builderGrouping" class="form-select form-select-sm mb-3"><option>Dependencia → Dirección → Unidad</option><option>Dependencia → Campaña</option><option>Dirección → Unidad → Responsable</option><option>Estado → Fecha límite</option><option>Sin agrupación</option></select>
                    <label class="form-label small fw-bold">Formato de salida</label><select id="builderFormat" class="form-select form-select-sm"><option>PDF institucional</option><option>Excel analítico</option><option>CSV para integración</option><option>Vista web e impresión</option></select>
                    <div class="builder-actions"><button type="button" class="btn btn-primary btn-sm" id="builderPreview">Actualizar vista previa</button><button type="button" class="btn btn-outline-success btn-sm" id="builderUse">Usar esta configuración</button></div>
                </div>
                <div class="report-box builder-panel">
                    <h3>2. Columnas visibles</h3>
                    @foreach(['agency'=>'Dependencia','unit'=>'Dirección / Unidad','title'=>'Campaña o carga','responsible'=>'Responsable','expected'=>'Evidencias esperadas','received'=>'Evidencias recibidas','validated'=>'Evidencias validadas','pending'=>'Pendientes','observed'=>'Observadas','close'=>'Fecha límite','status'=>'Estado','risk'=>'Riesgo','progress'=>'Avance'] as $key=>$label)
                        <label class="builder-option"><input type="checkbox" class="builder-column" value="{{ $key }}" checked><span><strong>{{ $label }}</strong><small>Incluir esta columna en la vista y exportación seleccionada.</small></span></label>
                    @endforeach
                </div>
                <div class="report-box builder-panel">
                    <h3>3. Filtros incluidos</h3>
                    @foreach(['agency'=>'Dependencia seleccionada','organizational_unit'=>'Dirección o unidad','campaign'=>'Campaña o periodo','status'=>'Estado de la evidencia','responsible'=>'Responsable operativo','date'=>'Rango de fechas'] as $key=>$label)
                        <label class="builder-option"><input type="checkbox" class="builder-filter" value="{{ $key }}" checked><span><strong>{{ $label }}</strong><small>Mostrar este filtro en la cabecera del reporte.</small></span></label>
                    @endforeach
                    <label class="builder-option"><input type="checkbox" id="builderSubtotals" checked><span><strong>Incluir subtotales</strong><small>Subtotal por cada grupo y total institucional.</small></span></label>
                </div>
                <div class="report-box builder-panel">
                    <h3>4. Vista previa de la plantilla</h3>
                    <div class="builder-preview"><h4 id="builderPreviewTitle">Reporte ejecutivo institucional</h4><p class="small text-muted mb-2" id="builderPreviewMeta">Dependencia → Dirección → Unidad · PDF institucional</p><strong class="small">Columnas seleccionadas</strong><ul id="builderPreviewColumns"></ul><strong class="small">Filtros visibles</strong><ul id="builderPreviewFilters"></ul></div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>En esta primera versión las plantillas son configuraciones de trabajo del usuario. La persistencia en base de datos y programación de envíos queda para la siguiente fase.</p>
                </div>
            </div>
        </div>
        <script>
        (()=>{const labels={agency:'Dependencia',unit:'Dirección / Unidad',title:'Campaña o carga',responsible:'Responsable',expected:'Evidencias esperadas',received:'Evidencias recibidas',validated:'Evidencias validadas',pending:'Pendientes',observed:'Observadas',close:'Fecha límite',status:'Estado',risk:'Riesgo',progress:'Avance'};const filterLabels={agency:'Dependencia seleccionada',organizational_unit:'Dirección o unidad',campaign:'Campaña o periodo',status:'Estado de la evidencia',responsible:'Responsable operativo',date:'Rango de fechas'};const type=document.getElementById('builderReportType'),group=document.getElementById('builderGrouping'),format=document.getElementById('builderFormat'),title=document.getElementById('builderPreviewTitle'),meta=document.getElementById('builderPreviewMeta'),cols=document.getElementById('builderPreviewColumns'),filters=document.getElementById('builderPreviewFilters');function refresh(){title.textContent=type.options[type.selectedIndex].text;meta.textContent=group.value+' · '+format.value;cols.innerHTML=[...document.querySelectorAll('.builder-column:checked')].map(x=>'<li>'+labels[x.value]+'</li>').join('')||'<li>Sin columnas seleccionadas</li>';filters.innerHTML=[...document.querySelectorAll('.builder-filter:checked')].map(x=>'<li>'+filterLabels[x.value]+'</li>').join('')||'<li>Sin filtros seleccionados</li>';if(document.getElementById('builderSubtotals').checked)filters.innerHTML+='<li>Subtotales y total institucional</li>'}function preset(name){document.querySelectorAll('.builder-column').forEach(x=>x.checked=name==='executive'||(name==='pending'&&['agency','unit','title','responsible','pending','observed','close','status','risk'].includes(x.value))||(name==='audit'&&['agency','unit','title','responsible','status','close'].includes(x.value)));document.querySelectorAll('.builder-filter').forEach(x=>x.checked=true);type.value=name==='pending'?'pending':name==='audit'?'audit':'executive';group.value=name==='audit'?'Estado → Fecha límite':name==='pending'?'Dependencia → Campaña':'Dependencia → Dirección → Unidad';format.value='PDF institucional';refresh()}document.getElementById('builderPreview').addEventListener('click',refresh);document.querySelectorAll('.builder-column,.builder-filter,#builderSubtotals').forEach(x=>x.addEventListener('change',refresh));document.querySelectorAll('[data-preset]').forEach(x=>x.addEventListener('click',()=>preset(x.dataset.preset)));document.getElementById('builderUse').addEventListener('click',()=>{const target=type.value;window.location.href='{{ route('reports.index') }}?report='+target});refresh()})();
        <script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.querySelector('.report-toolbar');
 const from=form?.querySelector('[name="from"]'),to=form?.querySelector('[name="to"]');
 if(!form||!from||!to)return;
 const setRange=(kind)=>{
   const now=new Date(); let start=new Date(now), end=new Date(now);
   if(kind==='week'){const day=(now.getDay()+6)%7;start.setDate(now.getDate()-day);}
   if(kind==='month')start=new Date(now.getFullYear(),now.getMonth(),1);
   if(kind==='quarter'){const qm=Math.floor(now.getMonth()/3)*3;start=new Date(now.getFullYear(),qm,1);}
   const fmt=d=>d.toISOString().slice(0,7);
   if(kind!=='today' || kind==='today'){from.value=fmt(start);to.value=fmt(end);}
   form.submit();
 };
 document.querySelectorAll('[data-report-range]').forEach(btn=>btn.addEventListener('click',()=>{
   const kind=btn.dataset.reportRange;
   if(kind==='custom'){from.focus();return;}
   setRange(kind);
 }));
});
</script></script>
    @elseif($report === 'executive')
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
                <div class="report-section"><div class="report-section-title">Focos de atención</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Pauta / carga</th><th>Fecha límite</th><th>Estado</th><th>Riesgo</th></tr></thead><tbody>@forelse($reportRows->filter(fn($r)=>$r['pending']>0 || $r['observed']>0 || in_array($r['status'],['VENCIDA','REPROGRAMADA'],true))->take(8) as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td><strong>{{ $row['pauta'] }}</strong><br><span class="small text-muted">Carga: {{ $row['title'] }}</span></td><td>{{ $row['close'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td><span class="badge {{ $row['risk']==='ALTO' ? 'danger' : 'warn' }}">{{ $row['risk'] }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No hay focos de atención en el universo actual.</td></tr>@endforelse</tbody></table></div>
                <div class="report-total"><div><span>Esperadas</span><strong>{{ number_format($summary['expected']) }}</strong></div><div><span>Recibidas</span><strong>{{ number_format($summary['received']) }}</strong></div><div><span>Validadas</span><strong>{{ number_format($summary['validated']) }}</strong></div><div><span>Pendientes</span><strong>{{ number_format($summary['pending']) }}</strong></div><div><span>Observadas</span><strong>{{ number_format($summary['observed']) }}</strong></div><div><span>Entrega</span><strong>{{ $summary['delivery_percentage'] }}%</strong></div></div>
            </div>
        </div>
    @elseif($report === 'compliance')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Cumplimiento por dependencia, dirección y unidad</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Pauta / carga</th><th class="num">Esperadas</th><th class="num">Recibidas</th><th class="num">Validadas</th><th class="num">Pendientes</th><th class="num">Cumplimiento</th></tr></thead><tbody>@forelse($reportRows as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td><strong>{{ $row['pauta'] }}</strong><br><span class="small text-muted">Carga: {{ $row['title'] }}</span></td><td class="num">{{ $row['expected'] }}</td><td class="num">{{ $row['received'] }}</td><td class="num">{{ $row['validated'] }}</td><td class="num">{{ $row['pending'] }}</td><td class="num"><span class="badge {{ $row['expected'] && $row['received']/$row['expected'] >= .8 ? 'ok' : ($row['expected'] && $row['received']/$row['expected'] >= .5 ? 'warn' : 'danger') }}">{{ $row['expected'] ? round($row['received']*100/$row['expected'],1) : 0 }}%</span></td></tr>@empty<tr><td colspan="8" class="empty">No hay registros para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @elseif($report === 'pending')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Seguimiento de pendientes y vencimientos</div><div class="report-subtitle">Solo se muestran cargas con evidencias pendientes, observadas, rechazadas o con riesgo operativo.</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Pauta / carga</th><th>Responsable</th><th>Fecha límite</th><th class="num">Pendientes</th><th class="num">Observadas</th><th>Estado</th><th>Riesgo</th></tr></thead><tbody>@forelse($reportRows->filter(fn($r)=>$r['pending']>0 || $r['observed']>0 || in_array($r['status'],['VENCIDA','REPROGRAMADA'],true)) as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td><strong>{{ $row['pauta'] }}</strong><br><span class="small text-muted">Carga: {{ $row['title'] }}</span></td><td>{{ $row['responsible'] }}</td><td>{{ $row['close'] }}</td><td class="num">{{ $row['pending'] }}</td><td class="num">{{ $row['observed'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td><span class="badge {{ $row['risk']==='ALTO' ? 'danger' : 'warn' }}">{{ $row['risk'] }}</span></td></tr>@empty<tr><td colspan="9" class="empty">No hay pendientes para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @elseif($report === 'evidence')
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Detalle de evidencias y expedientes</div><table class="report-table"><thead><tr><th>Dependencia</th><th>Dirección / Unidad</th><th>Pauta / carga</th><th>Responsable</th><th>Apertura</th><th>Cierre</th><th>Estado</th><th class="num">Esperadas</th><th class="num">Recibidas</th><th class="num">Validadas</th></tr></thead><tbody>@forelse($reportRows as $row)<tr><td>{{ $row['agency'] }}</td><td>{{ $row['unit'] }}</td><td><strong>{{ $row['pauta'] }}</strong><br><span class="small text-muted">Carga: {{ $row['title'] }}</span></td><td>{{ $row['responsible'] }}</td><td>{{ $row['open'] }}</td><td>{{ $row['close'] }}</td><td>{{ $statusLabels[$row['status']] ?? $row['status'] }}</td><td class="num">{{ $row['expected'] }}</td><td class="num">{{ $row['received'] }}</td><td class="num">{{ $row['validated'] }}</td></tr>@empty<tr><td colspan="10" class="empty">No hay evidencias para los filtros seleccionados.</td></tr>@endforelse</tbody></table></div></div>
    @else
        <div class="report-box report-preview"><div class="report-section" style="margin-top:0"><div class="report-section-title">Historial de cambios de estado</div><table class="report-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Dependencia</th><th>Carga</th><th>Estado anterior</th><th>Estado nuevo</th><th>Motivo</th></tr></thead><tbody>@forelse($statusHistory as $item)<tr><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td><td>{{ $item->user?->name ?? 'Sistema' }}</td><td>{{ $item->scheduledLoad?->agency?->name ?? '—' }}</td><td>{{ $item->scheduledLoad?->title ?? '—' }}</td><td>{{ $statusLabels[$item->old_status] ?? $item->old_status ?? '—' }}</td><td>{{ $statusLabels[$item->new_status] ?? $item->new_status }}</td><td>{{ $item->reason ?: '—' }}</td></tr>@empty<tr><td colspan="7" class="empty">No hay cambios de estado en el universo seleccionado.</td></tr>@endforelse</tbody></table></div><div class="report-section"><div class="report-section-title">Eventos de auditoría</div><table class="report-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Entidad</th><th>ID</th></tr></thead><tbody>@forelse($auditRows as $item)<tr><td>{{ $item->created_at?->format('d/m/Y H:i') }}</td><td>{{ $item->user?->name ?? 'Sistema' }}</td><td>{{ $item->event }}</td><td>{{ class_basename($item->entity_type) }}</td><td>{{ $item->entity_id ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="empty">No hay eventos de auditoría en el universo seleccionado.</td></tr>@endforelse</tbody></table></div></div>
    @endif
</div>
@endsection
