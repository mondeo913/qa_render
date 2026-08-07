@extends('layouts.app')
@section('title','Auditoría')
@section('page-title','Bitácora de auditoría')
@section('content')
<div class="card siget-card"><div class="card-header"><div><h2>Eventos auditables</h2><p>Usuario, entidad, cambio y solicitud</p></div></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Entidad</th><th>ID</th><th>Valores</th></tr></thead><tbody>@foreach($logs as $log)<tr><td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->user?->name }}</td><td>{{ $log->event }}</td><td>{{ class_basename($log->entity_type) }}</td><td>{{ $log->entity_id }}</td><td><code>{{ Str::limit(json_encode($log->new_values),90) }}</code></td></tr>@endforeach</tbody></table></div><div class="card-footer">{{ $logs->links() }}</div></div>
@endsection
