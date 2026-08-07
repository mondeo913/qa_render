@extends('layouts.app')
@section('title','Plantillas')
@section('page-title','Plantillas y requisitos de evidencia')
@section('content')
<div class="row g-4">
<div class="col-xl-8">@foreach($templates as $template)<div class="card siget-card mb-3"><div class="card-header"><div><h2>{{ $template->name }}</h2><p>{{ $template->code }} · versión {{ $template->version }}</p></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Requisito</th><th>Dirección</th><th>Extensiones</th><th>Archivos</th><th>Máximo</th></tr></thead><tbody>@foreach($template->requirements as $req)<tr><td>{{ $req->name }}<small>{{ $req->code }}</small></td><td>{{ $req->responsibleUnit?->name }}</td><td>{{ implode(', ',$req->allowed_extensions ?? []) }}</td><td>{{ $req->min_files }}–{{ $req->max_files }}</td><td>{{ $req->max_size_mb }} MB</td></tr>@endforeach</tbody></table></div></div>@endforeach</div>
<div class="col-xl-4"><div class="card siget-card"><div class="card-header"><div><h2>Agregar requisito</h2><p>Entrega asignada a una dirección</p></div></div><div class="card-body"><form method="POST" action="{{ route('admin.templates.requirements.store') }}">@csrf
<select name="template_id" class="form-select mb-2">@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select>
<input name="code" class="form-control mb-2" placeholder="Código" required><input name="name" class="form-control mb-2" placeholder="Nombre" required>
<select name="responsible_unit_id" class="form-select mb-2">@foreach($agencies as $agency)<optgroup label="{{ $agency->name }}">@foreach($agency->units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</optgroup>@endforeach</select>
<input name="allowed_extensions" class="form-control mb-2" value="pdf,xlsx,mp4" placeholder="Extensiones separadas por coma">
<div class="row g-2"><div class="col"><input name="min_files" type="number" value="1" class="form-control"></div><div class="col"><input name="max_files" type="number" value="3" class="form-control"></div><div class="col"><input name="max_size_mb" type="number" value="500" class="form-control"></div></div>
<button class="btn btn-primary w-100 mt-3">Guardar requisito</button></form></div></div></div>
</div>
@endsection
