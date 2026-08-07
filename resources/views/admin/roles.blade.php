@extends('layouts.app')
@section('title','Roles')
@section('page-title','Matriz de roles y permisos')
@section('content')
<div class="accordion" id="rolesAccordion">
@foreach($roles as $role)
<div class="accordion-item">
<h2 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#role{{ $role->id }}">{{ $role->name }} <span class="badge text-bg-light ms-2">{{ $role->permissions->count() }} permisos</span></button></h2>
<div id="role{{ $role->id }}" class="accordion-collapse collapse" data-bs-parent="#rolesAccordion"><div class="accordion-body"><form method="POST" action="{{ route('admin.roles.update',$role) }}">@csrf @method('PUT')
@foreach($permissions as $module=>$items)<h3 class="h6 text-uppercase text-secondary mt-3">{{ $module }}</h3><div class="row">@foreach($items as $permission)<div class="col-md-4"><label class="form-check"><input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission))><span>{{ $permission->name }}</span></label></div>@endforeach</div>@endforeach
<button class="btn btn-primary mt-3">Guardar permisos</button></form></div></div></div>
@endforeach
</div>
@endsection
