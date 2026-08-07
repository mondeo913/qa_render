@extends('layouts.app')
@section('content')<h1 class="h3">Incidentes operativos</h1>
<div class="card siget-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Código</th><th>Severidad</th><th>Título</th><th>Estado</th><th>Apertura</th></tr></thead><tbody>
@foreach($incidents as $i)<tr><td>{{$i->code}}</td><td>{{$i->severity}}</td><td>{{$i->title}}</td><td>{{$i->status}}</td><td>{{$i->opened_at}}</td></tr>@endforeach
</tbody></table></div></div>{{$incidents->links()}}@endsection
