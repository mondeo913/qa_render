@extends('layouts.app')
@section('title','Usuarios')
@section('page-title','Administración de usuarios')
@section('content')
<div class="row g-4 siget-admin-users">
<div class="col-xl-4"><div class="card siget-card"><div class="card-header"><div><h2>Nuevo usuario</h2><p>Una cuenta por persona; permisos y alcances por perfil</p></div></div><div class="card-body"><form method="POST" action="{{ route('admin.users.store') }}">@csrf
<div class="mb-2"><input name="name" class="form-control" placeholder="Nombre" required></div>
<div class="mb-2"><input name="email" type="email" class="form-control" placeholder="Correo de acceso completo" required></div>
<div class="mb-2"><input name="password" type="password" class="form-control" placeholder="Contraseña (10+)" required></div>
<div class="mb-2"><select name="role_id" class="form-select" required>@foreach($roles->reject(fn($role) => in_array($role->code, ['DIRECTOR', 'OPERADOR'], true)) as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
<div class="mb-2"><select name="contracting_agency_id" class="form-select"><option value="">Sin dependencia</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}">{{ $agency->name }}</option>@endforeach</select></div>
<div class="mb-3"><select name="organizational_unit_id" class="form-select"><option value="">Sin dirección</option>@foreach($agencies as $agency)<optgroup label="{{ $agency->name }}">@foreach($agency->units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</optgroup>@endforeach</select></div>
<div class="form-check mb-3"><input type="hidden" name="general_profile" value="0"><input class="form-check-input" type="checkbox" name="general_profile" value="1" id="new-general-profile" checked><label class="form-check-label" for="new-general-profile">Perfil general para todas las dependencias</label><div class="form-text">Los operativos conservan su dirección y responsable. Fiscalización requiere asignación de expediente.</div></div>
<button class="btn btn-primary w-100">Crear usuario</button></form></div></div></div>
<div class="col-xl-8"><div class="card siget-card"><div class="card-header"><div><h2>Usuarios registrados</h2><p>Cuenta general, perfil, dependencias autorizadas y estado</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuario</th><th>Rol</th><th>Alcance</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>@foreach($users as $user)
@php($editing = $errors->any() && (int)old('_editing_user_id') === (int)$user->id)
<tr id="user-row-{{ $user->id }}">
<td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td>
<td>{{ $user->role?->name }}</td>
<td><span class="d-block">{{ $user->organizationalUnit?->name ?: 'Alcance institucional' }}</span><div class="siget-user-scopes">@forelse($user->scopes->pluck('agency')->filter()->unique('id') as $scopeAgency)<span class="badge text-bg-secondary">{{ $scopeAgency->code }}</span>@empty<span>{{ $user->agency?->name ?: 'Según permisos del perfil' }}</span>@endforelse</div></td>
<td>{{ $user->status }}</td>
<td class="text-end">
<div class="d-flex justify-content-end gap-1 flex-wrap">
<button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleUserEdit({{ $user->id }})">Editar</button>
<form method="POST" action="{{ route('admin.users.toggle',$user) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">{{ $user->status==='ACTIVE'?'Desactivar':'Activar' }}</button></form>
<form method="POST" action="{{ route('admin.users.destroy',$user) }}" onsubmit="return confirm('¿Desea eliminar definitivamente a {{ addslashes($user->name) }}? Esta acción no se puede deshacer.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Eliminar</button></form>
</div>
</td>
</tr>
<tr id="user-edit-{{ $user->id }}" class="{{ $editing ? '' : 'd-none' }} bg-light">
<td colspan="5">
<form method="POST" action="{{ route('admin.users.update',$user) }}" class="row g-2 align-items-end">@csrf @method('PATCH')
<input type="hidden" name="_editing_user_id" value="{{ $user->id }}">
<div class="col-md-6"><label class="form-label small">Nombre</label><input name="name" value="{{ $editing ? old('name', $user->name) : $user->name }}" class="form-control form-control-sm" required></div>
<div class="col-md-6"><label class="form-label small">Correo de acceso</label><input name="email" type="email" value="{{ $editing ? old('email', $user->email) : $user->email }}" class="form-control form-control-sm" required><div class="form-text">Correo completo, por ejemplo enlace_institucional@siget.local.</div></div>
<div class="col-md-4"><label class="form-label small">Rol</label><select name="role_id" class="form-select form-select-sm" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((int)($editing ? old('role_id', $user->role_id) : $user->role_id) === (int)$role->id)>{{ $role->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label small">Dependencia</label><select name="contracting_agency_id" class="form-select form-select-sm"><option value="">Sin dependencia</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((int)($editing ? old('contracting_agency_id', $user->contracting_agency_id) : $user->contracting_agency_id) === (int)$agency->id)>{{ $agency->name }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label small">Dirección</label><select name="organizational_unit_id" class="form-select form-select-sm"><option value="">Sin dirección</option>@foreach($agencies as $agency)<optgroup label="{{ $agency->name }}">@foreach($agency->units as $unit)<option value="{{ $unit->id }}" @selected((int)($editing ? old('organizational_unit_id', $user->organizational_unit_id) : $user->organizational_unit_id) === (int)$unit->id)>{{ $unit->name }}</option>@endforeach</optgroup>@endforeach</select></div>
<div class="col-md-6"><label class="form-label small">Nueva contraseña <span class="text-muted">(opcional, 10+)</span></label><input name="password" type="password" class="form-control form-control-sm" minlength="10" placeholder="Dejar vacío para conservarla"></div>
<div class="col-12 form-check ms-2"><input type="hidden" name="general_profile" value="0"><input class="form-check-input" type="checkbox" name="general_profile" value="1" id="general-profile-{{ $user->id }}" @checked(($user->metadata['standard_profile'] ?? null) === $user->role?->code)><label class="form-check-label" for="general-profile-{{ $user->id }}">Perfil general para todas las dependencias de su dirección</label></div>
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
