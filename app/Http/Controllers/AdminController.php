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

        return view('admin.users', [
            'users' => User::query()
                ->with(['role', 'agency', 'organizationalUnit'])
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

    public function storeUser(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10'],
            'role_id' => ['required', 'exists:roles,id'],
            'contracting_agency_id' => ['nullable', 'exists:contracting_agencies,id'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
        ]);

        User::query()->create($data + [
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Usuario creado.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request, 'users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:10'],
            'role_id' => ['required', 'exists:roles,id'],
            'contracting_agency_id' => ['nullable', 'exists:contracting_agencies,id'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Usuario actualizado correctamente.');
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

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request, 'users.manage');

        abort_if($user->is($request->user()), 422, 'No puede desactivar su propia cuenta.');

        $user->update([
            'status' => $user->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ]);

        return back()->with('success', 'Estado del usuario actualizado.');
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

        return view('admin.agencies', [
            'agencies' => ContractingAgency::query()
                ->with('units')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeAgency(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:contracting_agencies,code'],
            'name' => ['required', 'string', 'max:220'],
            'legal_name' => ['nullable', 'string', 'max:260'],
        ]);

        ContractingAgency::query()->create($data + ['active' => true]);

        return back()->with('success', 'Dependencia creada.');
    }

    public function updateAgency(Request $request, ContractingAgency $agency): RedirectResponse
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

        return back()->with('success', 'Dependencia actualizada.');
    }

    public function destroyAgency(Request $request, ContractingAgency $agency): RedirectResponse
    {
        $this->authorizeAdmin($request, 'agencies.manage');

        try {
            DB::transaction(function () use ($agency): void {
                $agencyId = $agency->id;
                $unitIds = DB::table('organizational_units')->where('contracting_agency_id', $agencyId)->pluck('id');
                $templateIds = DB::table('evidence_templates')->where('contracting_agency_id', $agencyId)->pluck('id');
                $importIds = DB::table('calendar_imports')->where('contracting_agency_id', $agencyId)->pluck('id');
                $loadIds = DB::table('scheduled_loads')->where('contracting_agency_id', $agencyId)->pluck('id');
                $folderIds = DB::table('repository_folders')->where('contracting_agency_id', $agencyId)->pluck('id');

                if ($loadIds->isNotEmpty()) {
                    DB::table('load_closures')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('institutional_reviews')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('load_status_history')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('notifications')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('accounting_notices')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('review_assignments')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('load_reschedules')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('evidences')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('signed_documents')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('scheduled_load_deliverables')->whereIn('scheduled_load_id', $loadIds)->delete();
                    DB::table('repository_folders')->whereIn('scheduled_load_id', $loadIds)->update(['scheduled_load_id' => null]);
                    DB::table('scheduled_loads')->whereIn('id', $loadIds)->delete();
                }

                if ($importIds->isNotEmpty()) {
                    DB::table('calendar_import_rows')->whereIn('calendar_import_id', $importIds)->delete();
                    DB::table('calendar_imports')->whereIn('id', $importIds)->delete();
                }

                DB::table('calendar_suspensions')->where('contracting_agency_id', $agencyId)->delete();
                DB::table('template_requirements')->whereIn('template_id', $templateIds)->delete();
                DB::table('evidence_templates')->whereIn('id', $templateIds)->delete();

                if ($unitIds->isNotEmpty()) {
                    DB::table('scheduled_load_deliverables')->whereIn('organizational_unit_id', $unitIds)->delete();
                    DB::table('template_requirements')->whereIn('responsible_unit_id', $unitIds)->update(['responsible_unit_id' => null]);
                    DB::table('users')->whereIn('organizational_unit_id', $unitIds)->update(['organizational_unit_id' => null]);
                    DB::table('repository_folders')->whereIn('organizational_unit_id', $unitIds)->update(['organizational_unit_id' => null]);
                    DB::table('organizational_units')->whereIn('id', $unitIds)->update(['parent_id' => null]);
                    DB::table('organizational_units')->whereIn('id', $unitIds)->delete();
                }

                DB::table('user_scopes')->where('contracting_agency_id', $agencyId)->delete();
                DB::table('users')->where('contracting_agency_id', $agencyId)->update(['contracting_agency_id' => null]);

                if ($folderIds->isNotEmpty()) {
                    DB::table('evidences')->whereIn('folder_id', $folderIds)->update(['folder_id' => null]);
                    DB::table('signed_documents')->whereIn('folder_id', $folderIds)->update(['folder_id' => null]);
                    DB::table('evidence_files')->whereIn('folder_id', $folderIds)->update(['folder_id' => null]);
                    DB::table('repository_folders')->whereIn('id', $folderIds)->update(['parent_id' => null]);
                    DB::table('repository_folders')->whereIn('id', $folderIds)->delete();
                }

                $agency->delete();
            });
        } catch (QueryException) {
            return back()->withErrors([
                'agency' => 'No se pudo completar la eliminación en cascada. La operación fue revertida para proteger la integridad de los datos.',
            ]);
        }

        return back()->with('success', 'Dependencia eliminada.');
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
                ->with(['requirements', 'requirements.responsibleUnit'])
                ->latest('version')
                ->get(),
            'agencies' => ContractingAgency::query()->with('units')->get(),
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
