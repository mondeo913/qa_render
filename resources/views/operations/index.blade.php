@extends('layouts.app')
@section('title','Centro de Operaciones')
@section('page-title','Centro de Operaciones SIGET')
@section('content')
<div class="row g-3 mb-4">
@foreach([
 ['Estado general',$health['overall_status'],'bi-heart-pulse'],
 ['Disponibilidad mensual',$sla['availability_percent'].'%','bi-graph-up'],
 ['Incidentes',$sla['incident_count'],'bi-exclamation-triangle'],
 ['MTTR',$sla['mttr_minutes'].' min','bi-stopwatch']
] as [$label,$value,$icon])
<div class="col-md-3"><div class="siget-intelligence-kpi"><i class="bi {{$icon}}"></i><span>{{$label}}</span><strong>{{$value}}</strong></div></div>
@endforeach
</div>
<div class="row g-4">
<div class="col-lg-5"><div class="card siget-card h-100"><div class="card-header"><div><h2>Salud de componentes</h2><p>Base, cola y repositorio</p></div></div><div class="card-body"><canvas id="operationsHealth" height="190"></canvas></div></div></div>
<div class="col-lg-7"><div class="card siget-card h-100"><div class="card-header"><div><h2>Incidentes recientes</h2><p>Severidad, estado y apertura</p></div><a href="{{route('operations.incidents')}}" class="btn btn-sm btn-outline-primary">Ver todos</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Código</th><th>Incidente</th><th>Severidad</th><th>Estado</th></tr></thead><tbody>@forelse($incidents as $incident)<tr><td>{{$incident->code}}</td><td>{{$incident->title}}<small>{{$incident->opened_at?->format('d/m/Y H:i')}}</small></td><td>{{$incident->severity}}</td><td>{{$incident->status}}</td></tr>@empty<tr><td colspan="4" class="text-secondary text-center py-4">Sin incidentes.</td></tr>@endforelse</tbody></table></div></div></div>
<div class="col-12"><div class="card siget-card"><div class="card-header"><div><h2>Respaldos recientes</h2><p>Integridad y verificación</p></div><a href="{{route('operations.backups')}}" class="btn btn-sm btn-outline-primary">Historial</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Tamaño</th><th>Verificado</th></tr></thead><tbody>@foreach($backups as $backup)<tr><td>{{$backup->backup_type}}</td><td>{{$backup->status}}</td><td>{{$backup->started_at?->format('d/m/Y H:i')}}</td><td>{{number_format(($backup->size_bytes??0)/1048576,2)}} MB</td><td>{{$backup->verified_at?->format('d/m/Y H:i') ?? 'Pendiente'}}</td></tr>@endforeach</tbody></table></div></div></div>
</div>
<script type="application/json" data-siget-chart="operationsHealth">{!! json_encode(['type'=>'bar','labels'=>['Base ms','Cola pendiente','Cola fallida','Archivos repositorio'],'datasets'=>[['label'=>'Valor','data'=>[$health['database']['latency_ms'],$health['queue']['pending'],$health['queue']['failed'],$health['storage']['files']]]]]) !!}</script>
@endsection
