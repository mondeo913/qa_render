<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Services\DashboardAnalyticsService;
use App\Support\RolePresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): View
    {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $agencies = ContractingAgency::query()->where('active', true)->orderBy('name')->get();

        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->when(!empty($filters['agency_id']), fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id']))
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

        return view('dashboard.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            // Aliases exclusivos para los filtros: no alteran consultas, permisos ni flujo.
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'presentation' => RolePresentation::for($request->user()->role?->code),
        ]);
    }
}
