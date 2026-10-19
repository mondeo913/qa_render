@extends('layouts.app')
@section('title', $presentation['title'].' · SIGET')
@section('page-title', $presentation['title'])
@section('page-subtitle', $presentation['subtitle'])
@section('content')
@php
    $snapshot=$analytics['workspace']; $s=$snapshot['summary']; $sent=$analytics['evidence_summary']; $k=$analytics['kpis'];
    $user=auth()->user(); $access=app(\App\Services\AccessScopeService::class);
    $groups=['pending'=>'Pendiente de envío','review'=>'En revisión','observed'=>'Con observaciones','validated'=>'Validado / cerrado'];
    $colors=['pending'=>'#0369a1','review'=>'#7c3aed','observed'=>'#b91c1c','validated'=>'#15803d'];
    $link=fn(array $extra=[])=>route('dashboard', array_filter(array_merge($filters,$extra), fn($v)=>$v!==null && $v!=='' )).'#dashboard-work';
    $dataset=fn($label,$data,$color)=>['label'=>$label,'data'=>array_values($data),'backgroundColor'=>$color,'borderColor'=>$color];
    $barOptions=['indexAxis'=>'y','scales'=>['x'=>['beginAtZero'=>true,'ticks'=>['precision'=>0]],'y'=>['grid'=>['display'=>false]]]];
    $byAgency=collect($snapshot['by_agency']); $byResponsible=collect($snapshot['by_responsible']);
    $composition=['type'=>'doughnut','semanticColors'=>true,'labels'=>array_values($groups),'datasets'=>[$dataset('Entregables',array_map(fn($key)=>$s[$key],array_keys($groups)),array_values($colors))], 'links'=>array_map(fn($key)=>$link(['deliverable_group'=>$key]),array_keys($groups)), 'options'=>['cutout'=>'68%']];
    $agencyChart=['type'=>'bar','semanticColors'=>true,'labels'=>$byAgency->pluck('name')->all(),'datasets'=>array_map(fn($key)=>$dataset($groups[$key],$byAgency->pluck($key)->all(),$colors[$key]),array_keys($groups)), 'links'=>$byAgency->map(fn($r)=>$link(['agency_id'=>$r['id'],'pauta_id'=>null]))->all(),'options'=>$barOptions];
    $responsibleChart=['type'=>'bar','semanticColors'=>true,'labels'=>$byResponsible->pluck('name')->all(),'datasets'=>array_map(fn($key)=>$dataset($groups[$key],$byResponsible->pluck($key)->all(),$colors[$key]),['pending','review','observed']), 'links'=>$byResponsible->map(fn($r)=>$r['id'] ? $link(['responsible_id'=>$r['id']]) : null)->all(),'options'=>$barOptions];
    $directions=collect($analytics['evidence_by_direction']);
    $comparison=['type'=>'bar','semanticColors'=>true,'labels'=>$directions->pluck('unit')->all(),'datasets'=>[$dataset('Esperados',$directions->pluck('expected')->all(),'#0369a1'),$dataset('Enviados',$directions->pluck('received')->all(),'#7c3aed'),$dataset('Validados',$directions->pluck('validated')->all(),'#15803d')], 'options'=>$barOptions];
    $trend=collect($analytics['evidence_trend']);
    $trendChart=['type'=>'bar','semanticColors'=>true,'labels'=>$trend->pluck('period')->all(),'datasets'=>[$dataset('Esperados',$trend->pluck('expected')->all(),'#0369a1'),$dataset('Enviados',$trend->pluck('received')->all(),'#7c3aed'),$dataset('Validados',$trend->pluck('validated')->all(),'#15803d')], 'options'=>['scales'=>['y'=>['beginAtZero'=>true,'ticks'=>['precision'=>0]]]]];
    $due=collect($analytics['pending_by_due']);
    $dueChart=['type'=>'bar','semanticColors'=>true,'labels'=>$due->pluck('bucket')->all(),'datasets'=>[$dataset('Pendientes de envío',$due->pluck('total')->all(),'#0369a1')],'options'=>$barOptions];
    $cards=match($profile) {
        'admin'=>[['Usuarios activos',$administration['active_users'],'Cuentas habilitadas'],['Trabajos pendientes',$administration['queue']['pending'],'Cola de procesamiento'],['Trabajos fallidos',$administration['queue']['failed'],'Revisar en operaciones'],['Base de datos',$administration['database']['status']==='UP'?'Disponible':'Revisar','Consulta actual · '.$administration['database']['latency_ms'].' ms']],
        'institutional'=>[['En revisión',$s['review'],'Entregables enviados a revisión'],['Con observaciones',$s['observed'],'Entregables por corregir'],['Pendientes de firma',$analytics['signature_pending'],'Expedientes en etapa de firma'],['Expedientes cerrados',$k['closed'],'Cierre institucional concluido']],
        'operator'=>[['Pendientes de envío',$s['pending'],'Incluye borradores y correcciones'],['Con observaciones',$s['observed'],'Revisar motivos y corregir'],['En revisión',$s['review'],'Enviados a la Dirección o al enlace'],['Fuera de plazo',$s['overdue'],'Entregables sin resolver al vencer']],
        'fiscal'=>[['Por fiscalizar',$snapshot['fiscal']['pending'],'Versión actual sin dictamen propio'],['Aprobados en fiscalización',$snapshot['fiscal']['approved'],'Dictamen propio de la versión actual'],['Observados en fiscalización',$snapshot['fiscal']['observed'],'Observación o rechazo propio'],['Aún sin envío',$snapshot['fiscal']['not_submitted'],'Entregables todavía no fiscalizables']],
        default=>[['Entregables esperados',$s['expected'],'Requisitos del alcance seleccionado'],['Entregables enviados',$sent['received'],'Con al menos un envío registrado'],['En revisión',$s['review'],'Dirección o revisión institucional'],['Fuera de plazo',$s['overdue'],'Pendientes u observados sin resolver']],
    };
@endphp
<div class="siget-dashboard" data-dashboard-profile="{{ $profile }}" data-expected="{{ $s['expected'] }}" data-status-total="{{ $s['pending']+$s['review']+$s['observed']+$s['validated'] }}">
    <header class="siget-dashboard-hero">
        <div><span class="siget-dashboard-overline">SIGET · {{ $profile==='executive' ? 'Dashboard ejecutivo' : 'Seguimiento por perfil' }}</span><h2>{{ $presentation['title'] }}</h2><p>{{ $presentation['subtitle'] }}</p></div>
        <div class="siget-dashboard-actions"><span class="exec-scope"><i class="bi bi-shield-check"></i> {{ $presentation['scope'] }}</span>
            <a class="btn btn-primary" href="{{ route($presentation['action']['route']) }}"><i class="bi {{ $presentation['action']['icon'] }} me-1"></i>{{ $presentation['action']['label'] }}</a>
            @if($user->hasPermission('reports.export'))<a class="btn btn-outline-primary" href="{{ route('reports.pdf', $filters) }}">Exportar resumen</a>@endif
        </div>
    </header>
    @include('dashboard.partials.filters')
    <div class="siget-dashboard-kpis">
        @foreach($cards as [$label,$value,$hint])<article class="siget-dashboard-kpi"><h3>{{ $label }}</h3><strong>{{ is_numeric($value) ? number_format($value,0,',',' ') : $value }}</strong><p>{{ $hint }}</p></article>@endforeach
    </div>
    <p class="siget-dashboard-scope-note"><strong>{{ $k['total'] }} cargas · {{ $s['expected'] }} entregables</strong> en este alcance. Los archivos adjuntos y sus versiones forman parte de cada entregable. Actualizado {{ now()->format('d/m/Y H:i') }}.</p>
    @if($profile==='admin')
        <section class="siget-dashboard-grid">
            <article class="siget-dashboard-card"><header><h3>Alertas de administración</h3><p>Incidencias abiertas y trabajos que requieren atención.</p></header>
                <p>{{ $administration['queue']['failed'] }} trabajos fallidos · {{ $administration['queue']['pending'] }} pendientes.</p>
                @forelse($administration['incidents'] as $incident)<p><strong>{{ $incident->title }}</strong><br>{{ $incident->severity }} · {{ $incident->status }}</p>@empty<p class="text-secondary">Sin incidencias abiertas registradas.</p>@endforelse
                <a href="{{ route('operations.index') }}" class="btn btn-outline-primary">Consultar servicios y procesos</a>
            </article>
            <article class="siget-dashboard-card"><header><h3>Actividad reciente</h3><p>Últimos eventos de auditoría del sistema.</p></header>
                @forelse($administration['activity'] as $activity)<p><strong>{{ $activity->event }}</strong><br>{{ $activity->user?->name ?? 'Sistema' }} · {{ $activity->created_at?->format('d/m/Y H:i') }}</p>@empty<p class="text-secondary">Sin eventos registrados.</p>@endforelse
                <a href="{{ route('admin.logs') }}" class="btn btn-outline-primary">Abrir auditoría</a>
            </article>
        </section>
    @endif
    @if($profile==='institutional')
        <section class="siget-dashboard-grid">
            <article class="siget-dashboard-card"><header><h3>Pautas por confirmar o corregir</h3><p>Importaciones pendientes de las dependencias autorizadas; esta lista no usa el periodo de las cargas.</p></header>
                @forelse($pendingImports as $import)<p><a href="{{ route('calendar.import.preview', $import) }}">{{ $import->original_filename }}</a><br>{{ $import->status==='VALIDATED' ? 'Lista para confirmar' : 'Requiere corregir errores de importación' }}</p>@empty<p class="text-secondary">Sin importaciones pendientes.</p>@endforelse
                <a href="{{ route('calendar.index') }}" class="btn btn-outline-primary">Calendario y pautas</a>
            </article>
            <article class="siget-dashboard-card"><header><h3>Revisión, firma y cierre</h3><p>Expedientes que continúan hacia el cierre institucional.</p></header>
                @forelse($analytics['institutional_queue'] as $load)<p><a href="{{ route('loads.show',$load) }}">{{ $load->agency?->name }} · {{ $load->title }}</a><br>{{ str_replace('_',' ', $load->status->value) }}</p>@empty<p class="text-secondary">Sin expedientes en estas etapas.</p>@endforelse
            </article>
        </section>
    @endif
    @if($profile==='fiscal')
        @php
            $fiscalLabels=['pending'=>'Por fiscalizar','approved'=>'Aprobados','observed'=>'Observados','not_submitted'=>'Aún sin envío'];
            $fiscalChart=['type'=>'doughnut','semanticColors'=>true,'labels'=>array_values($fiscalLabels),'datasets'=>[$dataset('Entregables asignados',array_map(fn($key)=>$snapshot['fiscal'][$key],array_keys($fiscalLabels)),['#7c3aed','#15803d','#b91c1c','#64748b'])]];
            $age=$snapshot['fiscal_age'];
            $ageChart=['type'=>'bar','semanticColors'=>true,'labels'=>array_keys($age),'datasets'=>[$dataset('Por fiscalizar',array_values($age),'#7c3aed')],'options'=>$barOptions];
        @endphp
        <section class="siget-dashboard-grid">
            @include('dashboard.partials.chart',['id'=>'dashboardFiscalChart','title'=>'Estado de mis asignaciones','description'=>'Dictámenes propios de la versión actual; no modifica el estado institucional.','chart'=>$fiscalChart])
            @include('dashboard.partials.chart',['id'=>'dashboardFiscalAgeChart','title'=>'Antigüedad pendiente de fiscalización','description'=>'Tiempo desde la asignación o el envío más reciente, para versiones sin dictamen propio.','chart'=>$ageChart])
        </section>
    @else
        <section class="siget-dashboard-grid siget-dashboard-main-charts">
            @include('dashboard.partials.chart',['id'=>'dashboardAgencyChart','title'=>'Cumplimiento por dependencia','description'=>'Cantidad de entregables por estado actual. Seleccione una dependencia para abrir su detalle.','chart'=>$agencyChart,'height'=>max(290,count($agencyChart['labels'])*70)])
            @include('dashboard.partials.chart',['id'=>'dashboardStatusChart','title'=>'Estado actual de los entregables','description'=>$s['expected'].' entregables en cuatro grupos excluyentes. La suma coincide con los esperados.','chart'=>$composition])
        </section>
    @endif
    @if(in_array($profile,['executive','admin','institutional'],true))
        <section class="siget-dashboard-grid">
            @include('dashboard.partials.chart',['id'=>'dashboardDirectionChart','title'=>'Comparativo por dirección','description'=>'Barras separadas: esperados, enviados al menos una vez y actualmente validados. Son etapas acumulativas.','chart'=>$comparison])
            @include('dashboard.partials.chart',['id'=>'dashboardTrendChart','title'=>'Tendencia de entregas','description'=>'Últimos seis meses con entregables, por fecha límite. Las barras muestran cantidades.','chart'=>$trendChart])
        </section>
    @elseif($profile==='direction')
        <section class="siget-dashboard-grid">
            @include('dashboard.partials.chart',['id'=>'dashboardResponsibleChart','title'=>'Carga pendiente por responsable','description'=>'Pendientes, en revisión y observados dentro de su Dirección.','chart'=>$responsibleChart,'height'=>max(290,count($responsibleChart['labels'])*65)])
            @include('dashboard.partials.chart',['id'=>'dashboardTrendChart','title'=>'Tendencia de entregas','description'=>'Esperados, enviados y validados por mes de fecha límite.','chart'=>$trendChart])
        </section>
    @elseif($profile==='operator')
        @include('dashboard.partials.chart',['id'=>'dashboardDueChart','title'=>'Pendientes por fecha límite','description'=>'Incluye borradores y correcciones aún sin reenviar. Consulte cada ventana en su expediente.','chart'=>$dueChart])
    @endif
    <section class="siget-dashboard-card" id="dashboard-work">
        <header class="siget-dashboard-table-heading"><div><h3>{{ match($profile){'operator'=>'Mis entregables y correcciones','fiscal'=>'Mis asignaciones de fiscalización','institutional'=>'Bandeja de revisión de entregables',default=>'Seguimiento de entregables'} }}</h3><p>{{ $snapshot['queue']->count() }} de {{ $snapshot['queue_total'] }} entregables. Primero los vencidos, observados y en revisión; fiscalización prioriza versiones sin dictamen.</p></div>
            @if($user->hasPermission('calendar.view'))<a class="btn btn-outline-primary" href="{{ route('calendar.index', array_filter(['contracting_agency_id'=>$filters['agency_id'] ?? null,'date'=>!empty($filters['from']) ? $filters['from'].'-01' : null])) }}">Calendario de fechas programadas</a>@endif
        </header>
        <div class="table-responsive"><table class="table siget-dashboard-table align-middle"><thead><tr><th>Dependencia y pauta</th><th>Entregable / responsable</th><th>Fecha límite</th><th>Estado actual</th><th>Seguimiento</th></tr></thead><tbody>
            @forelse($snapshot['queue'] as $deliverable)
                @php
                    $load=$deliverable->scheduledLoad; $group=\App\Services\DashboardSnapshotService::group($deliverable); $deadline=\App\Services\DashboardSnapshotService::deadline($deliverable); $expired=\App\Services\DashboardSnapshotService::overdue($deliverable); $evidence=$deliverable->evidences->sortByDesc('id')->first();
                    $reviewable=$evidence && $access->canReviewEvidence($user,$evidence) && match($profile){'direction'=>$deliverable->status->value==='ENVIADO','institutional'=>$deliverable->status->value==='EN_REVISION','fiscal'=>($deliverable->dashboard_fiscal_state==='pending'),default=>false};
                @endphp
                <tr data-deliverable-id="{{ $deliverable->id }}" data-status-group="{{ $group }}"><td><strong>{{ $load->agency?->name }}</strong><small>{{ $load->calendarImport?->original_filename ?? 'Carga sin importación' }}</small><a href="{{ route('loads.show',$load) }}">{{ $load->title }}</a></td><td>{{ $deliverable->templateRequirement?->name ?? 'Entregable' }}<small>{{ $deliverable->responsibleUser?->name ?? 'Sin asignar' }}</small><small>{{ $deliverable->organizationalUnit?->name }}</small></td><td>{{ $deadline?->format('d/m/Y H:i') ?? 'Sin fecha' }}@if($expired)<small class="text-danger">Plazo vencido</small>@endif</td><td><span class="siget-dashboard-status" style="--status-color:{{ $colors[$group] }}">{{ $groups[$group] }}</span><small>{{ str_replace('_',' ', $deliverable->status->value) }}</small>@if($profile==='fiscal')<small>Fiscalización: {{ $fiscalLabels[$deliverable->dashboard_fiscal_state] }}</small>@endif</td><td><a class="btn btn-sm btn-outline-primary" href="{{ $reviewable ? route('evidences.show',$evidence) : route('loads.show',$load) }}">{{ $reviewable ? ($profile==='fiscal' ? 'Fiscalizar' : 'Revisar evidencia') : ($profile==='operator' && in_array($group,['pending','observed'],true) ? 'Abrir captura' : 'Ver expediente') }}</a></td></tr>
            @empty<tr><td colspan="5" class="text-center py-4">Sin entregables para los filtros y permisos actuales.</td></tr>@endforelse
        </tbody></table></div>
        @if($snapshot['queue_total']>15)<p>Se muestran los primeros 15. Use los filtros para localizar otra dependencia, pauta o responsable.</p>@endif
    </section>
</div>
@endsection
