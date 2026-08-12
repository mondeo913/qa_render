<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MyLoadsController extends Controller
{
    public function __invoke(Request $request, AccessScopeService $access): View
    {
        $user = $request->user();
        $unitIds = $access->accessibleUnitIds($user);

        $baseQuery = $access->scopeLoads(
            ScheduledLoad::query()
                ->with([
                    'agency',
                    'template',
                    'deliverables' => function ($query) use ($access, $user, $unitIds) {
                        if ($unitIds !== []) {
                            $access->scopeDeliverables($query, $user);
                        }
                        $query->with([
                            'organizationalUnit',
                            'templateRequirement',
                            'evidences.files',
                        ]);
                    },
                ]),
            $user
        );

        if ($request->filled('agency_id')) {
            $baseQuery->where('contracting_agency_id', $request->integer('agency_id'));
        }

        if ($request->filled('template_id')) {
            $baseQuery->where('template_id', $request->integer('template_id'));
        }

        if ($request->filled('month')) {
            $month = $request->string('month')->toString();
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $baseQuery
                    ->whereDate('effective_open_at', '>=', $month . '-01')
                    ->whereDate('effective_open_at', '<', date('Y-m-d', strtotime($month . '-01 +1 month')));
            }
        }

        if ($request->filled('unit_id')) {
            $unitId = $request->integer('unit_id');
            $baseQuery->whereHas('deliverables', function ($query) use ($unitId, $access, $user, $unitIds) {
                if ($unitIds !== []) {
                    $access->scopeDeliverables($query, $user);
                }
                $query->where('organizational_unit_id', $unitId);
            });
        }

        $loads = (clone $baseQuery)
            ->orderByDesc('effective_open_at')
            ->paginate(18)
            ->withQueryString();

        $filterLoads = (clone $baseQuery)
            ->without(['deliverables'])
            ->with(['agency', 'template'])
            ->orderByDesc('effective_open_at')
            ->get();

        $filterUnits = (clone $baseQuery)
            ->without(['deliverables'])
            ->with(['deliverables.organizationalUnit'])
            ->get()
            ->flatMap(fn ($load) => $load->deliverables->pluck('organizationalUnit'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $agencies = $filterLoads->pluck('agency')->filter()->unique('id')->sortBy('name')->values();
        $templates = $filterLoads->pluck('template')->filter()->unique('id')->sortBy('name')->values();
        $months = $filterLoads
            ->pluck('effective_open_at')
            ->filter()
            ->map(fn ($date) => $date->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();

        return view('cargas.mis-cargas', compact(
            'loads',
            'agencies',
            'templates',
            'months',
            'filterUnits'
        ));
    }
}
