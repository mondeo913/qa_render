@extends('layouts.app')
@section('content')<h1 class="h3">Respaldos</h1>
<div class="card siget-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Tamaño</th><th>Verificado</th></tr></thead><tbody>
@foreach($backups as $b)<tr><td>{{$b->backup_type}}</td><td>{{$b->status}}</td><td>{{$b->started_at}}</td><td>{{number_format(($b->size_bytes??0)/1073741824,2)}} GB</td><td>{{$b->verified_at??'Pendiente'}}</td></tr>@endforeach
</tbody></table></div></div>{{$backups->links()}}@endsection
