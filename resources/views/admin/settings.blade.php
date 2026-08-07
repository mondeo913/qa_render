@extends('layouts.app')
@section('title','Configuración')
@section('page-title','Configuración del sistema')
@section('content')
<div class="row g-4"><div class="col-lg-5"><div class="card siget-card"><div class="card-header"><div><h2>Guardar parámetro</h2></div></div><div class="card-body"><form method="POST" action="{{ route('admin.settings.update') }}">@csrf<input name="key" class="form-control mb-2" placeholder="clave.del.parametro" required><textarea name="value" class="form-control mb-2" placeholder="Valor" required></textarea><textarea name="description" class="form-control mb-3" placeholder="Descripción"></textarea><button class="btn btn-primary w-100">Guardar</button></form></div></div></div><div class="col-lg-7"><div class="card siget-card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Clave</th><th>Valor</th><th>Descripción</th></tr></thead><tbody>@foreach($settings as $setting)<tr><td><code>{{ $setting->key }}</code></td><td>{{ data_get($setting->value,'value') }}</td><td>{{ $setting->description }}</td></tr>@endforeach</tbody></table></div></div></div></div>
@endsection
