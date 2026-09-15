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
<div class="col-xl-8"><div class="card siget-card"><div class="card-header"><div><h2>Usuarios registrados</h2><p>Roles, dependencia, dirección y estado</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Alcance</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>@foreach($users as $user)
<tr id="user-row-{{ $user->id }}">
<td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td>
<td>{{ $user->role?->name }}</td>
<td>{{ $user->agency?->name }}<small>{{ $user->organizationalUnit?->name }}</small></td>
<td>{{ $user->status }}</td>
<td class="text-end">
<div class="d-flex justify-content-end gap-1 flex-wrap">
<button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleUserEdit({{ $user->id }})">Editar</button>
<form method="POST" action="{{ route('admin.users.toggle',$user) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $user->status==='ACTIVE'?'Desactivar':'Activar' }}</button></form>
<form method="POST" action="{{ route('admin.users.destroy',$user) }}" onsubmit="return confirm('¿Desea eliminar definitivamente a {{ addslashes($user->name) }}? Esta acción no se puede deshacer.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
</div>
</td>
</tr>
<tr id="user-edit-{{ $user->id }}" class="d-none bg-light">
<td colspan="5">
<form method="POST" action="{{ route('admin.users.update',$user) }}" class="row g-2 align-items-end">@csrf @method('PATCH')
<div class="col-md-6"><label class="form-label small">Nombre</label><input name="name" value="{{ $user->name }}" class="form-control form-control-sm" required></div>
<div class="col-md-6"><label class="form-label small">Correo</label><input name="email" type="email" value="{{ $user->email }}" class="form-control form-control-sm" required></div>
<div class="col-md-4"><label class="form-label small">Rol</label><select name="role_id" class="form-select form-select-sm" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected($role->id === $user->role_id)>{{ $role->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label small">Dependencia</label><select name="contracting_agency_id" class="form-select form-select-sm"><option value="">Sin dependencia</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected($agency->id === $user->contracting_agency_id)>{{ $agency->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label small">Dirección</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Sin dirección</option>@foreach($agencies as $agency)<optgroup label="{{ $agency->name }}">@foreach($agency->units as $unit)<option value="{{ $unit->id }}" @selected($unit->id === $user->organizational_unit_id)>{{ $unit->name }}</option>@endforeach</optgroup>@endforeach</select></div>
<div class="col-md-6"><label class="form-label small">Nueva contraseña <span class="text-muted">(opcional, 10+)</span></label><input name="password" type="password" class="form-control form-control-sm" minlength="10" placeholder="Dejar vacío para conservarla"></div>
<div class="col-md-6 d-flex gap-2"><button class="btn btn-sm btn-success">Guardar</button><button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleUserEdit({{ $user->id }})">Cancelar</button></div>
</form>
</td>
</tr>
@endforeach</tbody></table></div><div class="card-footer">{{ $users->links() }}</div></div></div>
</div>
@push('scripts')
<script>
function toggleUserEdit(id) {
    const row = document.getElementById(`user-edit-${id}`);
    if (row) row.classList.toggle('d-none');
}
</script>
@endpush
@endsection
