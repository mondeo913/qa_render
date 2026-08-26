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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($data): void {
            $agency = ContractingAgency::query()->create(
                $data + ['active' => true]
            );

            $this->ensureAgencyDirections($agency);
        });

        return back()->with(
            'success',
            'Dependencia creada con sus dos direcciones institucionales.'
        );
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


    public function updateAgency(
        Request $request,
        ContractingAgency $agency
    ): RedirectResponse {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('contracting_agencies', 'code')
                    ->ignore($agency->id),
            ],
            'name' => ['required', 'string', 'max:220'],
            'legal_name' => ['nullable', 'string', 'max:260'],
            'active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($agency, $data): void {
            $agency->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'active' => array_key_exists('active', $data)
                    ? (bool) $data['active']
                    : $agency->active,
            ]);

            $this->ensureAgencyDirections($agency);
        });

        return back()->with(
            'success',
            'Dependencia actualizada.'
        );
    }

    private function ensureAgencyDirections(
        ContractingAgency $agency
    ): void {
        $directions = [
            [
                'code' => 'DIR_A',
                'name' => 'Dirección de Transmisión',
            ],
            [
                'code' => 'DIR_B',
                'name' => 'Dirección de Programación y Continuidad',
            ],
        ];

        foreach ($directions as $direction) {
            OrganizationalUnit::query()->firstOrCreate(
                [
                    'contracting_agency_id' => $agency->id,
                    'code' => $direction['code'],
                ],
                [
                    'parent_id' => null,
                    'name' => $direction['name'],
                    'unit_type' => 'DIRECTION',
                    'active' => true,
                ]
            );
        }
    }

    public function updateUnit(
        Request $request,
        OrganizationalUnit $unit
    ): RedirectResponse {
        $this->authorizeAdmin($request, 'agencies.manage');

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:70',
                Rule::unique('organizational_units', 'code')
                    ->where(
                        'contracting_agency_id',
                        $unit->contracting_agency_id
                    )
                    ->ignore($unit->id),
            ],
            'name' => ['required', 'string', 'max:220'],
            'unit_type' => [
                'required',
                Rule::in([
                    'DIRECTION',
                    'AREA',
                    'COORDINATION',
                ]),
            ],
            'active' => ['nullable', 'boolean'],
        ]);

        // DIR_A y DIR_B son direcciones institucionales estructurales.
        // Su código no puede cambiar porque identifica la dirección
        // utilizada por cargas, requisitos, usuarios y repositorios.
        if ($unit->code === 'DIR_A') {
            $data['code'] = 'DIR_A';
            $data['unit_type'] = 'DIRECTION';
        } elseif ($unit->code === 'DIR_B') {
            $data['code'] = 'DIR_B';
            $data['unit_type'] = 'DIRECTION';
        }

        $unit->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'unit_type' => $data['unit_type'],
            'active' => array_key_exists('active', $data)
                ? (bool) $data['active']
                : $unit->active,
        ]);

        return back()->with(
            'success',
            'Unidad organizacional actualizada.'
        );
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
