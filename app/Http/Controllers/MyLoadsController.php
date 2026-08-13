<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
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
        $operatorUnitId = RoleCode::isOperator($user->role?->code) && $user->organizational_unit_id
            ? (int) $user->organizational_unit_id
            : null;

        $scopedQuery = $access->scopeLoads(
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

        // Una pauta desaparece de Mis cargas cuando ya no existe ningún
        // entregable pendiente dentro del alcance real del usuario. Esto evita
        // que una evidencia subida por otra dirección o por otro operador
        // oculte la pauta que todavía corresponde a esta dirección.
        $scopedQuery->whereHas('deliverables', function ($query) use ($access, $user) {
            $access->scopeDeliverables($query, $user);
            $query->whereDoesntHave('evidences');
        });

        if ($request->filled('agency_id')) {
            $scopedQuery->where('contracting_agency_id', $request->integer('agency_id'));
        }

        if ($operatorUnitId !== null) {
            // El operador queda limitado a la dirección asignada al usuario.
            // Un unit_id manipulado nunca puede ampliar su alcance.
            $scopedQuery->whereHas('deliverables', function ($query) use ($access, $user, $operatorUnitId) {
                $access->scopeDeliverables($query, $user);
                $query->where('organizational_unit_id', $operatorUnitId)
                    ->whereDoesntHave('evidences');
            });
        } elseif ($request->filled('unit_id')) {
            $unitId = $request->integer('unit_id');
            $scopedQuery->whereHas('deliverables', function ($query) use ($unitId, $access, $user, $unitIds) {
                if ($unitIds !== []) {
                    $access->scopeDeliverables($query, $user);
                }
                $query->where('organizational_unit_id', $unitId)
                    ->whereDoesntHave('evidences');
            });
        }

        $baseQuery = clone $scopedQuery;

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

        $loads = (clone $baseQuery)
            ->orderByDesc('effective_open_at')
            ->orderByDesc('id')
            ->paginate(18)
            ->withQueryString();

        // Las opciones de filtros parten del mismo alcance pendiente y no
        // aplican template/month para poder construir dependencias dinámicas.
        $filterLoads = (clone $scopedQuery)
            ->without(['deliverables'])
            ->with(['agency', 'template'])
            ->orderByDesc('effective_open_at')
            ->orderByDesc('id')
            ->get();

        $filterUnits = (clone $scopedQuery)
            ->without(['deliverables'])
            ->with(['deliverables' => function ($query) use ($access, $user) {
                $access->scopeDeliverables($query, $user);
                $query->whereDoesntHave('evidences')->with('organizationalUnit');
            }])
            ->get()
            ->flatMap(fn ($load) => $load->deliverables->pluck('organizationalUnit'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        if ($operatorUnitId !== null) {
            $filterUnits = $filterUnits->where('id', $operatorUnitId)->values();
        }

        $agencies = $filterLoads->pluck('agency')->filter()->unique('id')->sortBy('name')->values();

        $selectedAgencyId = $request->filled('agency_id') ? $request->integer('agency_id') : null;
        $agencyFilterLoads = $selectedAgencyId !== null
            ? $filterLoads->where('contracting_agency_id', $selectedAgencyId)->values()
            : $filterLoads;

        $templates = $agencyFilterLoads->pluck('template')->filter()->unique('id')->sortBy('name')->values();
        $selectedTemplateId = $request->filled('template_id') ? $request->integer('template_id') : null;
        $templateFilterLoads = $selectedTemplateId !== null
            ? $agencyFilterLoads->where('template_id', $selectedTemplateId)->values()
            : $agencyFilterLoads;

        $months = $templateFilterLoads
            ->pluck('effective_open_at')
            ->filter()
            ->map(fn ($date) => $date->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();

        // Dependencia -> Pauta contratada -> Meses contratados.
        // La vista usa esta estructura para no ofrecer meses de otra dependencia
        // ni meses de otra pauta.
        $monthsByAgencyTemplate = $filterLoads
            ->groupBy('contracting_agency_id')
            ->map(fn ($agencyLoads) => $agencyLoads
                ->groupBy('template_id')
                ->map(fn ($templateLoads) => $templateLoads
                    ->pluck('effective_open_at')
                    ->filter()
                    ->map(fn ($date) => $date->format('Y-m'))
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->all()
                )
                ->all()
            )
            ->all();

        // Se conserva esta estructura por compatibilidad con la vista/URLs
        // existentes, pero ahora los datos respetan dependencia y alcance.
        $monthsByTemplate = $templateFilterLoads
            ->groupBy('template_id')
            ->map(fn ($templateLoads) => $templateLoads
                ->pluck('effective_open_at')
                ->filter()
                ->map(fn ($date) => $date->format('Y-m'))
                ->unique()
                ->sortDesc()
                ->values()
                ->all()
            )
            ->all();

        $isDirectionLocked = $operatorUnitId !== null;

        return view('cargas.mis-cargas', compact(
            'loads',
            'agencies',
            'templates',
            'months',
            'monthsByTemplate',
            'monthsByAgencyTemplate',
            'filterUnits',
            'isDirectionLocked'
        ));
    }
}
