<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
use App\Enums\RoleCode;
use App\Support\RolePresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics, AccessScopeService $access): View
    {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'pauta_id' => ['nullable', 'integer', 'min:1'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'responsible_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:60'],
            'deliverable_group' => ['nullable', 'in:pending,review,observed,validated'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);

        $user = $request->user();

        $accessibleLoads = $access->scopeLoads(ScheduledLoad::query(), $user);

        $isGlobalDashboard = in_array($user->role?->code, [
            RoleCode::ADMINISTRADOR->value,
            RoleCode::DIRECTOR_GENERAL->value,
        ], true);

        $agencies = $access->availableAgencies($user);

        $unitsQuery = OrganizationalUnit::query()
            ->where('organizational_units.active', true)
            ->where('organizational_units.unit_type', 'DIRECTION')
            ->whereIn('organizational_units.code', ['DIR_A', 'DIR_B']);
        if (!$isGlobalDashboard) {
            $scopedUnitIds = $access->accessibleUnitIds($user);
            if ($scopedUnitIds !== []) {
                $unitsQuery->whereIn('organizational_units.id', $scopedUnitIds);
            } else {
                $unitsQuery->whereIn('organizational_units.id', function ($q) use ($accessibleLoads) {
                    $q->select('scheduled_load_deliverables.organizational_unit_id')
                        ->from('scheduled_load_deliverables')
                        ->whereIn('scheduled_load_deliverables.scheduled_load_id', (clone $accessibleLoads)->select('scheduled_loads.id'))
                        ->whereNotNull('scheduled_load_deliverables.organizational_unit_id')
                        ->distinct();
                });
            }
        }

        // Direction IDs remain stable when changing dependency; the load query intersects both.
        $units = $unitsQuery
            ->orderBy('name')
            ->get()
            ->groupBy(function ($unit) {
                $name = preg_replace('/\s+/u', ' ', trim((string) $unit->name));
                return mb_strtolower($name);
            })
            ->map(function ($group) {
                $unit = $group->first();
                $unit->name = preg_replace('/\s+/u', ' ', trim((string) $unit->name));
                $unit->filter_unit_ids = $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                return $unit;
            })
            ->sortBy(fn ($unit) => mb_strtolower(trim((string) $unit->name)))
            ->values();

        $pautasQuery = (clone $accessibleLoads)
            ->where('scheduled_loads.status', '!=', 'CANCELADA')
            ->join('calendar_imports', 'calendar_imports.id', '=', 'scheduled_loads.calendar_import_id')
            ->join('contracting_agencies', 'contracting_agencies.id', '=', 'scheduled_loads.contracting_agency_id');

        if (!empty($filters['agency_id'])) {
            $pautasQuery->where('scheduled_loads.contracting_agency_id', (int) $filters['agency_id']);
        }
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $pautasQuery->whereIn('scheduled_loads.id', function ($q) use ($unitIds) {
                    $q->select('scheduled_load_id')
                        ->from('scheduled_load_deliverables')
                        ->whereIn('organizational_unit_id', $unitIds);
                });
            }
        }
        if (!empty($filters['responsible_id'])) {
            $pautasQuery->whereIn('scheduled_loads.id', function ($q) use ($filters) {
                $q->select('scheduled_load_id')
                    ->from('scheduled_load_deliverables')
                    ->where('responsible_user_id', (int) $filters['responsible_id']);
            });
        }

        $filterPautas = $pautasQuery
            ->select([
                'calendar_imports.id as pauta_id',
                'calendar_imports.original_filename',
                'contracting_agencies.id as agency_id',
                'contracting_agencies.name as agency_name',
            ])
            ->selectRaw('COUNT(DISTINCT scheduled_loads.id) AS load_count')
            ->groupBy('calendar_imports.id', 'calendar_imports.original_filename', 'contracting_agencies.id', 'contracting_agencies.name')
            ->orderBy('contracting_agencies.name')
            ->orderByDesc('calendar_imports.id')
            ->get()
            ->map(fn ($pauta) => [
                'value' => (string) ((int) $pauta->pauta_id),
                'label' => trim((string) $pauta->agency_name).' · '.trim((string) ($pauta->original_filename ?: 'Pauta sin nombre')),
                'name' => (string) ($pauta->original_filename ?: 'Pauta sin nombre'),
                'agency_id' => (int) $pauta->agency_id,
                'load_count' => (int) $pauta->load_count,
            ])
            ->values();


        $responsibleQuery = User::query()
            ->join('scheduled_load_deliverables', 'scheduled_load_deliverables.responsible_user_id', '=', 'users.id')
            ->whereIn('scheduled_load_deliverables.scheduled_load_id', (clone $accessibleLoads)->select('scheduled_loads.id'))
            ->where('users.status', 'ACTIVE')
            ->select('users.id', 'users.name')
            ->distinct();
        $access->scopeDeliverables($responsibleQuery, $user);
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $responsibleQuery->whereIn('scheduled_load_deliverables.organizational_unit_id', $unitIds);
            }
        }
        $filterResponsibles = $responsibleQuery->orderBy('users.name')->get();

        $viewData = [
            'analytics' => $analytics->forUser($user, $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'filterPautas' => $filterPautas,
            'filterResponsibles' => $filterResponsibles,
            'presentation' => RolePresentation::for($user->role?->code),
            'profile' => match ($user->role?->code) {
                'ADMINISTRADOR' => 'admin',
                'DIRECTOR_GENERAL' => 'executive',
                'ENLACE_INSTITUCIONAL' => 'institutional',
                'FISCALIZADOR' => 'fiscal',
                'DIRECTOR', 'DIRECTOR_TRANSMISION', 'DIRECTOR_PROGRAMACION_CONTINUIDAD' => 'direction',
                default => 'operator',
            },
        ];
        if ($user->role?->code === 'ADMINISTRADOR') {
            $viewData['administration'] = [
                'queue' => app(\App\Services\Operations\QueueMonitorService::class)->inspect(),
                'database' => app(\App\Services\Operations\DatabaseHealthService::class)->inspect(),
                'active_users' => User::query()->where('status', 'ACTIVE')->count(),
                'incidents' => \App\Models\OperationalIncident::query()->whereNotIn('status', ['RESOLVED', 'CLOSED'])->latest('opened_at')->limit(5)->get(),
                'activity' => \App\Models\AuditLog::query()->with('user:id,name')->latest('id')->limit(5)->get(),
            ];
        }
        if ($user->role?->code === 'ENLACE_INSTITUCIONAL') {
            $viewData['pendingImports'] = \App\Models\CalendarImport::query()
                ->whereIn('contracting_agency_id', $agencies->pluck('id'))
                ->when($filters['agency_id'] ?? null, fn ($q, $id) => $q->where('contracting_agency_id', $id))
                ->whereIn('status', ['VALIDATED', 'WITH_ERRORS'])->latest('id')->limit(5)->get();
        }
        return view('dashboard.workspace', $viewData);
    }
}
