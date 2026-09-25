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
            'campaign' => ['nullable', 'string', 'max:255'],
            'responsible_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);

        $user = $request->user();

        $accessibleLoads = $access->scopeLoads(ScheduledLoad::query(), $user);

        $isGlobalDashboard = in_array($user->role?->code, [
            RoleCode::ADMINISTRADOR->value,
            RoleCode::DIRECTOR_GENERAL->value,
        ], true);

        $agenciesQuery = ContractingAgency::query()->where('active', true);
        if (!$isGlobalDashboard) {
            $agenciesQuery->whereIn('id', (clone $accessibleLoads)->select('contracting_agency_id')->distinct());
        }
        $agencies = $agenciesQuery->orderBy('name')->get();

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

        if (!empty($filters['agency_id'])) {
            $unitsQuery->where('organizational_units.contracting_agency_id', (int) $filters['agency_id']);
        }

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

        $campaignsQuery = (clone $accessibleLoads)
            ->where('scheduled_loads.status', '!=', 'CANCELADA')
            ->whereNotNull('scheduled_loads.title')
            ->where('scheduled_loads.title', '!=', '');
        if (!empty($filters['agency_id'])) {
            $campaignsQuery->where('scheduled_loads.contracting_agency_id', (int) $filters['agency_id']);
        }
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $campaignsQuery->whereIn('scheduled_loads.id', function ($q) use ($unitIds) {
                    $q->select('scheduled_load_id')
                        ->from('scheduled_load_deliverables')
                        ->whereIn('organizational_unit_id', $unitIds);
                });
            }
        }
        if (!empty($filters['responsible_id'])) {
            $campaignsQuery->whereIn('scheduled_loads.id', function ($q) use ($filters) {
                $q->select('scheduled_load_id')
                    ->from('scheduled_load_deliverables')
                    ->where('responsible_user_id', (int) $filters['responsible_id']);
            });
        }
        $filterCampaigns = $campaignsQuery
            ->select('scheduled_loads.title')
            ->distinct()
            ->orderBy('scheduled_loads.title')
            ->pluck('scheduled_loads.title')
            ->values();

        $responsibleQuery = User::query()
            ->join('scheduled_load_deliverables', 'scheduled_load_deliverables.responsible_user_id', '=', 'users.id')
            ->whereIn('scheduled_load_deliverables.scheduled_load_id', (clone $accessibleLoads)->select('scheduled_loads.id'))
            ->where('users.status', 'ACTIVE')
            ->select('users.id', 'users.name')
            ->distinct();
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $responsibleQuery->whereIn('scheduled_load_deliverables.organizational_unit_id', $unitIds);
            }
        }
        $filterResponsibles = $responsibleQuery->orderBy('users.name')->get();

        $periodLoads = clone $accessibleLoads;
        if (!empty($filters['agency_id'])) {
            $periodLoads->where('scheduled_loads.contracting_agency_id', (int) $filters['agency_id']);
        }
        if (!empty($filters['organizational_unit_id'])) {
            $unitIds = collect(explode(',', (string) $filters['organizational_unit_id']))
                ->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
            if ($unitIds) {
                $periodLoads->whereIn('scheduled_loads.id', function ($q) use ($unitIds) {
                    $q->select('scheduled_load_id')
                        ->from('scheduled_load_deliverables')
                        ->whereIn('organizational_unit_id', $unitIds);
                });
            }
        }

        $periodBounds = (clone $periodLoads)
            ->selectRaw('MIN(scheduled_loads.effective_open_at) AS min_open_at')
            ->selectRaw('MAX(scheduled_loads.effective_close_at) AS max_close_at')
            ->first();

        $periodMin = $periodBounds?->min_open_at ? date('Y-m', strtotime($periodBounds->min_open_at)) : null;
        $periodMax = $periodBounds?->max_close_at ? date('Y-m', strtotime($periodBounds->max_close_at)) : null;

        $viewData = [
            'analytics' => $analytics->forUser($user, $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'filterCampaigns' => $filterCampaigns,
            'filterResponsibles' => $filterResponsibles,
            'periodMin' => $periodMin,
            'periodMax' => $periodMax,
            'presentation' => RolePresentation::for($user->role?->code),
        ];

        $dashboardRoles = [
            'ADMINISTRADOR',
            'DIRECTOR_GENERAL',
            'ENLACE_INSTITUCIONAL',
        ];

        if (in_array($user->role?->code, $dashboardRoles, true)) {
            return view('dashboard.executive', $viewData);
        }

        return view('dashboard.index', $viewData);
    }
}
