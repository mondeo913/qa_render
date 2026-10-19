<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Catalog;
use App\Models\CatalogItem;
use App\Models\ContractingAgency;
use App\Models\EvidenceTemplate;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\TemplateRequirement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }

    public function users(Request $request)
    {
        $this->authorizeAdmin($request, 'users.manage');

        if ($request->is('api/*')) {
            $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
            $page = User::with('role')->where(fn ($q) => $q->whereNull('metadata->qa_retired')->orWhere('metadata->qa_retired', false))
                ->orderBy('id')->paginate($request->integer('per_page', 25));
            return response()->json(['data' => $page->getCollection()->map(fn ($user) => \App\Http\Controllers\Api\ApiData::managedUser($user)),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
        }

        return view('admin.users', [
            'users' => User::query()
                ->with(['role', 'agency', 'organizationalUnit', 'scopes.agency'])
                ->where(fn ($query) => $query->whereNull('metadata->qa_retired')->orWhere('metadata->qa_retired', false))
                ->orderBy('name')
                ->paginate(30),
            'roles' => Role::query()->where('active', true)->orderBy('name')->get(),
            'agencies' => ContractingAgency::query()
                ->with('units')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeUser(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', app()->environment('production') ? \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols() : 'min:10'],
            'role_id' => ['required', 'exists:roles,id'],
            'contracting_agency_id' => ['nullable', 'exists:contracting_agencies,id'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
            'general_profile' => ['sometimes', 'boolean'],
        ], [
            'email.email' => 'Escriba un correo completo, por ejemplo enlace_institucional@siget.local. No puede terminar en @.',
            'email.unique' => 'Este correo de acceso ya pertenece a otro usuario.',
        ]);

        $this->validateUserAssignment($data);
        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create(\Illuminate\Support\Arr::except($data, 'general_profile') + ['status' => 'ACTIVE']);
            if (array_key_exists('general_profile', $data)) app(\App\Services\UserProfileScopeService::class)->apply($user, (bool) $data['general_profile']);
            return $user;
        });

        app(\App\Services\AuditService::class)->record('user.created', $user);
        return $request->is('api/*') ? response()->json(['data' => \App\Http\Controllers\Api\ApiData::managedUser($user->fresh())], 201)
            : back()->with('success', 'Usuario creado.');
    }

    public function updateUser(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'users.manage');
        abort_if(($user->metadata['qa_retired'] ?? false) === true, 422, 'Esta cuenta fue consolidada; utilice la cuenta general del perfil.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', app()->environment('production') ? \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols() : 'min:10'],
            'role_id' => ['required', 'exists:roles,id'],
            'contracting_agency_id' => ['nullable', 'exists:contracting_agencies,id'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
            'general_profile' => ['sometimes', 'boolean'],
        ], [
            'email.email' => 'Escriba un correo completo, por ejemplo enlace_institucional@siget.local. No puede terminar en @.',
            'email.unique' => 'Este correo de acceso ya pertenece a otro usuario.',
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data += $user->only(['contracting_agency_id', 'organizational_unit_id']);
        $this->validateUserAssignment($data, $user);
        $auditFields = ['name', 'email', 'role_id', 'contracting_agency_id', 'organizational_unit_id'];
        $before = $user->only($auditFields);
        DB::transaction(function () use ($user, $data) {
            $roleChanged = (int) $data['role_id'] !== (int) $user->role_id;
            $assignmentChanged = (array_key_exists('organizational_unit_id', $data) && (int) $data['organizational_unit_id'] !== (int) $user->organizational_unit_id)
                || (array_key_exists('contracting_agency_id', $data) && (int) $data['contracting_agency_id'] !== (int) $user->contracting_agency_id);
            $general = array_key_exists('general_profile', $data) ? (bool) $data['general_profile']
                : isset($user->metadata['standard_profile']);
            $user->update(\Illuminate\Support\Arr::except($data, 'general_profile'));
            $user->unsetRelation('role');
            if ($roleChanged || ($general && $assignmentChanged) || array_key_exists('general_profile', $data)) app(\App\Services\UserProfileScopeService::class)->apply($user, $general, $roleChanged || ($general && $assignmentChanged));
        });
        app(\App\Services\AuditService::class)->record('user.updated', $user, $before, $user->only($auditFields));

        return $request->is('api/*') ? response()->json(['data' => \App\Http\Controllers\Api\ApiData::managedUser($user->fresh())])
            : back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request, 'users.manage');

        abort_if($user->is($request->user()), 422, 'No puede eliminar su propia cuenta.');

        if (AuditLog::query()->where('user_id', $user->id)->exists()) {
            return back()->withErrors([
                'user' => 'No se puede eliminar este usuario porque tiene historial registrado. Use Desactivar para conservar la trazabilidad.',
            ]);
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'user' => 'No se puede eliminar este usuario porque existen registros relacionados. Use Desactivar para conservar la trazabilidad.',
            ]);
        }

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleUser(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'users.manage');
        abort_if(($user->metadata['qa_retired'] ?? false) === true, 422, 'Esta cuenta fue consolidada; utilice la cuenta general del perfil.');

        abort_if($user->is($request->user()), 422, 'No puede desactivar su propia cuenta.');

        if ($request->is('api/*')) {
            $request->validate(['status' => ['required', 'in:ACTIVE,INACTIVE']]);
            if ($user->status === $request->input('status')) return response()->json(['data' => \App\Http\Controllers\Api\ApiData::managedUser($user)]);
        }

        $user->update([
            'status' => $user->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);

        $user->forceFill(['remember_token' => null])->save();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        app(\App\Services\AuditService::class)->record('user.status_changed', $user, [], ['status' => $user->status]);
        return $request->is('api/*') ? response()->json(['data' => \App\Http\Controllers\Api\ApiData::managedUser($user->fresh())])
            : back()->with('success', 'Estado del usuario actualizado.');
    }

    private function validateUserAssignment(array $data, ?User $existingUser = null): void
    {
        $role = Role::query()->where('active', true)->find($data['role_id']);
        $unit = isset($data['organizational_unit_id']) ? OrganizationalUnit::query()->where('active', true)->find($data['organizational_unit_id']) : null;
        $errors = [];
        if (!$role) { $errors['role_id'] = 'Seleccione un rol activo.'; }
        if (isset($data['organizational_unit_id']) && !$unit) { $errors['organizational_unit_id'] = 'Seleccione una dirección activa.'; }
        if (isset($data['contracting_agency_id']) && !ContractingAgency::query()->where('active', true)->whereKey($data['contracting_agency_id'])->exists()) {
            $errors['contracting_agency_id'] = 'Seleccione una dependencia activa.';
        }
        if ($unit && isset($data['contracting_agency_id']) && (int) $unit->contracting_agency_id !== (int) $data['contracting_agency_id']) {
            $errors['organizational_unit_id'] = 'La dirección no corresponde a la dependencia.';
        }
        if (\App\Enums\RoleCode::isDirectionDirector($role?->code) || \App\Enums\RoleCode::isOperator($role?->code)) {
            if (!$unit) { $errors['organizational_unit_id'] = 'Este perfil requiere una dirección.'; }
            $expected = match ($role?->code) {
                'DIRECTOR_TRANSMISION', 'OPERADOR_TRANSMISION' => 'DIR_A',
                'DIRECTOR_PROGRAMACION_CONTINUIDAD', 'OPERADOR_PROGRAMACION_CONTINUIDAD' => 'DIR_B',
                default => $unit?->code,
            };
            if ($unit && $unit->code !== $expected) { $errors['organizational_unit_id'] = 'La dirección no corresponde al perfil.'; }
        }
        $hasExistingAgencyScopes = $existingUser?->role?->code === 'ENLACE_INSTITUCIONAL'
            && !(array_key_exists('general_profile', $data) && !$data['general_profile'] && isset($existingUser->metadata['standard_profile']))
            && $existingUser->scopes()->where('can_read', true)
                ->whereHas('agency', fn ($query) => $query->where('active', true))->exists();
        if ($role?->code === 'ENLACE_INSTITUCIONAL' && empty($data['contracting_agency_id']) && !$unit && !$hasExistingAgencyScopes && !($data['general_profile'] ?? false)) {
            $errors['contracting_agency_id'] = 'El enlace requiere una dependencia o dirección asignada.';
        }
        if ($errors) { throw \Illuminate\Validation\ValidationException::withMessages($errors); }
    }

    public function roles(Request $request)
    {
        $this->authorizeAdmin($request, 'roles.manage');

        return view('admin.roles', [
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()
                ->orderBy('module')
                ->orderBy('name')
                ->get()
                ->groupBy('module'),
        ]);
    }

    public function updateRole(
        Request $request,
        Role $role
    ): RedirectResponse {
        $this->authorizeAdmin($request, 'roles.manage');

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return back()->with('success', 'Permisos del rol actualizados.');
    }

    public function agencies(Request $request)
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        if ($request->is('api/*')) return response()->json(['data' => ContractingAgency::with('units')->orderBy('name')->get()->map(fn ($agency) =>
            $agency->only(['id', 'code', 'name', 'legal_name', 'active']) + ['units' => $agency->units->map(fn ($unit) => $unit->only(['id', 'code', 'name', 'active']))])]);

        return view('admin.agencies', [
            'agencies' => ContractingAgency::query()
                ->with('units')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeAgency(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:contracting_agencies,code'],
            'name' => ['required', 'string', 'max:220'],
            'legal_name' => ['nullable', 'string', 'max:260'],
        ]);

        $agency = DB::transaction(function () use ($data) {
            $agency = ContractingAgency::query()->create($data + ['active' => true]);
            app(\App\Services\AgencyWorkflowService::class)->provision($agency);
            return $agency;
        });

        return $request->is('api/*') ? response()->json(['data' => $agency->only(['id', 'code', 'name', 'legal_name', 'active'])], 201)
            : back()->with('success', 'Dependencia creada.');
    }

    public function updateAgency(Request $request, ContractingAgency $agency): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('contracting_agencies', 'code')->ignore($agency->id),
            ],
            'name' => ['required', 'string', 'max:220'],
            'legal_name' => ['nullable', 'string', 'max:260'],
        ]);

        $agency->update($data);

        return $request->is('api/*') ? response()->json(['data' => $agency->only(['id', 'code', 'name', 'legal_name', 'active'])])
            : back()->with('success', 'Dependencia actualizada.');
    }

    public function destroyAgency(Request $request, ContractingAgency $agency): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        $agency->update(['active' => false]);
        app(\App\Services\AuditService::class)->record('agency.archived', $agency);
        return $request->is('api/*') ? response()->json(['data' => $agency->only(['id', 'code', 'name', 'active'])])
            : back()->with('success', 'Dependencia desactivada; expedientes e historial conservados.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'contracting_agency_id' => ['required', 'exists:contracting_agencies,id'],
            'code' => [
                'required',
                'string',
                'max:70',
                Rule::unique('organizational_units', 'code')
                    ->where('contracting_agency_id', $request->integer('contracting_agency_id')),
            ],
            'name' => ['required', 'string', 'max:220'],
            'unit_type' => ['required', Rule::in(['DIRECTION', 'AREA', 'COORDINATION'])],
        ]);

        OrganizationalUnit::query()->create($data + ['active' => true]);

        return back()->with('success', 'Unidad organizacional creada.');
    }

    public function templates(Request $request)
    {
        $this->authorizeAdmin($request, 'templates.manage');

        return view('admin.templates', [
            'templates' => EvidenceTemplate::query()
                ->when($request->user()->role?->code !== 'ADMINISTRADOR', fn ($q) => $q->whereIn('contracting_agency_id', app(\App\Services\AccessScopeService::class)->readableAgencyIds($request->user())))
                ->with(['requirements', 'requirements.responsibleUnit'])
                ->latest('version')
                ->get(),
            'agencies' => ContractingAgency::query()->with('units')->when($request->user()->role?->code !== 'ADMINISTRADOR', fn ($q) => $q->whereIn('id', app(\App\Services\AccessScopeService::class)->readableAgencyIds($request->user())))->get(),
        ]);
    }

    public function storeRequirement(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'templates.manage');

        $data = $request->validate([
            'template_id' => ['required', 'exists:evidence_templates,id'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:240'],
            'responsible_unit_id' => ['required', 'exists:organizational_units,id'],
            'allowed_extensions' => ['required', 'string'],
            'min_files' => ['required', 'integer', 'min:1', 'max:20'],
            'max_files' => ['required', 'integer', 'min:1', 'max:20'],
            'max_size_mb' => ['required', 'integer', 'min:1', 'max:2048'],
        ]);

        $template = EvidenceTemplate::query()->findOrFail($data['template_id']);
        abort_unless(app(\App\Services\AccessScopeService::class)->canManageAgency($request->user(), (int) $template->contracting_agency_id), 403);
        if (!OrganizationalUnit::query()->whereKey($data['responsible_unit_id'])->where('contracting_agency_id', $template->contracting_agency_id)->where('active', true)->exists() || $data['max_files'] < $data['min_files']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['responsible_unit_id' => 'Verifique dirección de la dependencia y cantidades de archivos.']);
        }
        TemplateRequirement::query()->updateOrCreate(
            [
                'template_id' => $data['template_id'],
                'code' => strtoupper($data['code']),
            ],
            [
                'name' => $data['name'],
                'responsible_unit_id' => $data['responsible_unit_id'],
                'responsible_role_code' => 'OPERADOR',
                'required' => true,
                'requires_validation' => true,
                'min_files' => $data['min_files'],
                'max_files' => $data['max_files'],
                'max_size_mb' => $data['max_size_mb'],
                'allowed_extensions' => collect(
                    explode(',', $data['allowed_extensions'])
                )->map(fn ($item) => strtolower(trim($item)))
                    ->filter()
                    ->values()
                    ->all(),
                'active' => true,
            ]
        );

        return back()->with('success', 'Requisito de plantilla guardado.');
    }

    public function catalogs(Request $request)
    {
        $this->authorizeAdmin($request, 'catalogs.manage');

        return view('admin.catalogs', [
            'catalogs' => Catalog::query()->with('items')->orderBy('name')->get(),
        ]);
    }

    public function storeCatalog(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'catalogs.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:catalogs,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
        ]);

        Catalog::query()->create($data + ['active' => true]);

        return back()->with('success', 'Catálogo creado.');
    }

    public function storeCatalogItem(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'catalogs.manage');

        $data = $request->validate([
            'catalog_id' => ['required', 'exists:catalogs,id'],
            'code' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        CatalogItem::query()->updateOrCreate(
            [
                'catalog_id' => $data['catalog_id'],
                'code' => strtoupper($data['code']),
            ],
            [
                'name' => $data['name'],
                'sort_order' => $data['sort_order'] ?? 0,
                'active' => true,
            ]
        );

        return back()->with('success', 'Elemento de catálogo guardado.');
    }

    public function settings(Request $request)
    {
        $this->authorizeAdmin($request, 'settings.manage');

        return view('admin.settings', [
            'settings' => SystemSetting::query()->orderBy('key')->get(),
        ]);
    }

    public function updateSetting(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'settings.manage');

        $data = $request->validate([
            'key' => ['required', 'string', 'max:160'],
            'value' => ['required', 'string', 'max:4000'],
            'description' => ['nullable', 'string'],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => $data['key']],
            [
                'value' => ['value' => $data['value']],
                'description' => $data['description'] ?? null,
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Configuración actualizada.');
    }

    public function logs(Request $request)
    {
        $this->authorizeAdmin($request, 'logs.view');

        return view('admin.logs', [
            'logs' => AuditLog::query()
                ->with('user')
                ->latest()
                ->paginate(50),
        ]);
    }
}
