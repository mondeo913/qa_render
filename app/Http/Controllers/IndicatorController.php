<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IndicatorController extends Controller
{
    public function index(
        Request $request,
        DashboardAnalyticsService $analytics,
        AccessScopeService $access
    ): View {
        abort_unless($request->user()->hasPermission('indicators.view'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'indicator' => ['nullable', 'string', 'max:80'],
            'frequency' => ['nullable', 'in:diario,semanal,mensual,trimestral'],
        ]);

        $agencies = ContractingAgency::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Mantener el filtro de Dirección alineado con el alcance real del usuario.
        $accessibleUnitIds = $access->accessibleUnitIds($request->user());

        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->when(!empty($filters['agency_id']), fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id']))
            ->when($accessibleUnitIds !== [], fn ($q) => $q->whereIn('id', $accessibleUnitIds))
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($unit) => mb_strtolower(trim($unit->name)))
            ->map(function ($group) {
                $unit = $group->first();
                $unit->filter_unit_ids = $group->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                return $unit;
            })
            ->sortBy(fn ($unit) => mb_strtolower(trim($unit->name)))
            ->values();

        $analyticsData = $analytics->forUser($request->user(), $filters);

        // The Indicators view compares by Dirección. Prefer the normalized
        // direction-level dataset and keep the existing view contract intact.
        if (empty($analyticsData['unit_performance']) && !empty($analyticsData['direction_performance'])) {
            $analyticsData['unit_performance'] = $analyticsData['direction_performance'];
        }

        return view('reportes.indicadores', [
            'analytics' => $analyticsData,
            'filters' => $filters,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
        ]);
    }
}
