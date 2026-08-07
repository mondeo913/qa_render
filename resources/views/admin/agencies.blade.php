@extends('layouts.app')
@section('title','Dependencias')
@section('page-title','Dependencias y estructura organizacional')
@section('content')
<div class="row g-4">
<div class="col-lg-4"><div class="card siget-card mb-4"><div class="card-header"><div><h2>Nueva dependencia</h2></div></div><div class="card-body"><form method="POST" action="{{ route('admin.agencies.store') }}">@csrf<input name="code" class="form-control mb-2" placeholder="Código" required><input name="name" class="form-control mb-2" placeholder="Nombre" required><input name="legal_name" class="form-control mb-3" placeholder="Razón social"><button class="btn btn-primary w-100">Crear</button></form></div></div>
<div class="card siget-card"><div class="card-header"><div><h2>Nueva unidad</h2></div></div><div class="card-body"><form method="POST" action="{{ route('admin.units.store') }}">@csrf<select name="contracting_agency_id" class="form-select mb-2" required>@foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select><input name="code" class="form-control mb-2" placeholder="Código" required><input name="name" class="form-control mb-2" placeholder="Nombre" required><select name="unit_type" class="form-select mb-3"><option>DIRECTION</option><option>AREA</option><option>COORDINATION</option></select><button class="btn btn-primary w-100">Crear unidad</button></form></div></div></div>
<div class="col-lg-8">@foreach($agencies as $agency)<div class="card siget-card mb-3"><div class="card-header"><div><h2>{{ $agency->name }}</h2><p>{{ $agency->code }} · {{ $agency->legal_name }}</p></div></div><div class="list-group list-group-flush">@foreach($agency->units as $unit)<div class="list-group-item d-flex justify-content-between"><span>{{ $unit->name }}</span><span class="badge text-bg-light">{{ $unit->code }} · {{ $unit->unit_type }}</span></div>@endforeach</div></div>@endforeach</div>
</div>
@endsection
