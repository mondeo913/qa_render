<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): View
    {
        abort_unless($request->user()->hasPermission('intelligence.view'), 403);
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            // Puede contener varios IDs equivalentes cuando el catálogo tiene registros duplicados por nombre.
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $agencies = ContractingAgency::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // El filtro ejecutivo debe mostrar una sola Dirección / Unidad por nombre.
        // Si existen registros equivalentes, conservamos todos sus IDs en filter_unit_ids
        // para que seleccionar una Dirección no cambie ni pierda el universo de SIGET.
        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->when(!empty($filters['agency_id']), fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id']))
            ->orderBy('name')
            ->get()
            ->groupBy(function ($unit) {
                $name = preg_replace('/\s+/u', ' ', trim((string) $unit->name));
                return mb_strtolower($name);
            })
            ->map(function ($group) {
                $unit = $group->first();
                $unit->name = preg_replace('/\s+/u', ' ', trim((string) $unit->name));
                $unit->filter_unit_ids = $group->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                return $unit;
            })
            ->sortBy(fn ($unit) => mb_strtolower(trim((string) $unit->name)))
            ->values();

        return view('intelligence.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'agencies' => $agencies,
            'units' => $units,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'filters' => $filters,
        ]);
    }
}
