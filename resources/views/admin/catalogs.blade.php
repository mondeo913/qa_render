@extends('layouts.app')
@section('title','Catálogos')
@section('page-title','Catálogos configurables')
@section('content')
<div class="row g-4">
<div class="col-lg-4"><div class="card siget-card mb-4"><div class="card-header"><div><h2>Nuevo catálogo</h2></div></div><div class="card-body"><form method="POST" action="{{ route('admin.catalogs.store') }}">@csrf<input name="code" class="form-control mb-2" placeholder="Código" required><input name="name" class="form-control mb-2" placeholder="Nombre" required><textarea name="description" class="form-control mb-3" placeholder="Descripción"></textarea><button class="btn btn-primary w-100">Crear</button></form></div></div>
<div class="card siget-card"><div class="card-header"><div><h2>Nuevo elemento</h2></div></div><div class="card-body"><form method="POST" action="{{ route('admin.catalog-items.store') }}">@csrf<select name="catalog_id" class="form-select mb-2">@foreach($catalogs as $catalog)<option value="{{ $catalog->id }}">{{ $catalog->name }}</option>@endforeach</select><input name="code" class="form-control mb-2" placeholder="Código" required><input name="name" class="form-control mb-2" placeholder="Nombre" required><input name="sort_order" type="number" value="0" class="form-control mb-3"><button class="btn btn-primary w-100">Guardar</button></form></div></div></div>
<div class="col-lg-8">@foreach($catalogs as $catalog)<div class="card siget-card mb-3"><div class="card-header"><div><h2>{{ $catalog->name }}</h2><p>{{ $catalog->code }}</p></div></div><div class="list-group list-group-flush">@foreach($catalog->items as $item)<div class="list-group-item d-flex justify-content-between"><span>{{ $item->name }}</span><span>{{ $item->code }}</span></div>@endforeach</div></div>@endforeach</div>
</div>
@endsection
