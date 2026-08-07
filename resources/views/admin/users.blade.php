@extends('layouts.app')
@section('title','Usuarios')
@section('page-title','Administración de usuarios')
@section('content')
<div class="row g-4">
<div class="col-xl-4"><div class="card siget-card"><div class="card-header"><div><h2>Nuevo usuario</h2><p>Cuenta, rol y alcance base</p></div></div><div class="card-body"><form method="POST" action="{{ route('admin.users.store') }}">@csrf
<div class="mb-2"><input name="name" class="form-control" placeholder="Nombre" required></div>
<div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Correo" required></div>
<div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Contraseña (10+)" required></div>
<div class="mb-2"><select name="role_id" class="form-select" required>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
<div class="mb-2"><select name="contracting_agency_id" class="form-select"><option value="">Sin dependencia</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select></div>
<div class="mb-3"><select name="organizational_unit_id" class="form-select"><option value="">Sin dirección</option>@foreach($agencies as $agency)<optgroup label="{{ $agency->name }}">@foreach($agency->units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</optgroup>@endforeach</select></div>
<button class="btn btn-primary w-100">Crear usuario</button></form></div></div></div>
<div class="col-xl-8"><div class="card siget-card"><div class="card-header"><div><h2>Usuarios registrados</h2><p>Roles, dependencia, dirección y estado</p></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Alcance</th><th>Estado</th><th></th></tr></thead><tbody>@foreach($users as $user)<tr><td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td><td>{{ $user->role?->name }}</td><td>{{ $user->agency?->name }}<small>{{ $user->organizationalUnit?->name }}</small></td><td>{{ $user->status }}</td><td><form method="POST" action="{{ route('admin.users.toggle',$user) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $user->status==='ACTIVE'?'Desactivar':'Activar' }}</button></form></td></tr>@endforeach</tbody></table></div><div class="card-footer">{{ $users->links() }}</div></div></div>
</div>
@endsection
