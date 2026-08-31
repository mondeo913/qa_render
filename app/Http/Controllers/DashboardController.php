<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
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
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);

        $user = $request->user();

        // Los catálogos se construyen sobre el mismo universo de cargas al que
        // tiene acceso el usuario. Así se evita mostrar dependencias o direcciones
        // que no corresponden a su alcance y se evita duplicar direcciones homónimas.
        $accessibleLoads = $access->scopeLoads(ScheduledLoad::query(), $user);

        $agencies = ContractingAgency::query()
            ->where('active', true)
            ->whereIn('id', (clone $accessibleLoads)->select('contracting_agency_id')->distinct())
            ->orderBy('name')
            ->get();

        $unitsQuery = OrganizationalUnit::query()
            ->where('organizational_units.active', true)
            ->whereIn('organizational_units.id', function ($q) use ($accessibleLoads) {
                $q->select('scheduled_load_deliverables.organizational_unit_id')
                    ->from('scheduled_load_deliverables')
                    ->whereIn('scheduled_load_deliverables.scheduled_load_id', (clone $accessibleLoads)->select('scheduled_loads.id'))
                    ->whereNotNull('scheduled_load_deliverables.organizational_unit_id')
                    ->distinct();
            });

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

        // El periodo disponible se deriva de las fechas efectivas de apertura/cierre
        // de las pautas/cargas realmente accesibles. Al seleccionar dependencia se
        // ajusta el universo para que el rango mensual corresponda a sus contratos.
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

        return view('dashboard.index', [
            'analytics' => $analytics->forUser($user, $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'periodMin' => $periodMin,
            'periodMax' => $periodMax,
            'presentation' => RolePresentation::for($user->role?->code),
        ]);
    }
}
