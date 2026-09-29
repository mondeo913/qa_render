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
        $request->validate(['pauta_id' => ['nullable', 'integer', 'min:1']]);
        $unitIds = $access->accessibleUnitIds($user);
        $operatorUnitId = RoleCode::isOperator($user->role?->code) && $user->organizational_unit_id
            ? (int) $user->organizational_unit_id
            : null;

        $scopedQuery = $access->scopeLoads(
            ScheduledLoad::query()
                ->with([
                    'agency',
                    'template',
                    'calendarImport',
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

                if ($request->filled('pauta_id')) {
            $pautaId = $request->integer('pauta_id');
            $scopedQuery->where('calendar_import_id', $pautaId);
        }

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
            ->with(['agency', 'template', 'calendarImport'])
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

        // La Pauta es cada Excel/orden cargado por Enlace Institucional.
        // Se identifica por calendar_import_id, por lo que dos órdenes de la
        // misma dependencia quedan separadas aunque pertenezcan al mismo mes.
        $pautas = $agencyFilterLoads
            ->groupBy('calendar_import_id')
            ->map(function ($pautaLoads) {
                $first = $pautaLoads->first();

                return (object) [
                    'id' => (int) $first->calendar_import_id,
                    'name' => (string) ($first->calendarImport?->original_filename ?: 'Pauta sin nombre'),
                    'agency_id' => (int) $first->contracting_agency_id,
                    'agency_name' => (string) ($first->agency?->name ?: 'Sin dependencia'),
                    'load_count' => $pautaLoads->count(),
                ];
            })
            ->sortByDesc('id')
            ->values();

        $templates = $agencyFilterLoads->pluck('template')->filter()->unique('id')->sortBy('name')->values();
        $selectedTemplateId = $request->filled('template_id') ? $request->integer('template_id') : null;
        $templateFilterLoads = $selectedTemplateId !== null
            ? $agencyFilterLoads->where('template_id', $selectedTemplateId)->values()
            : $agencyFilterLoads;

        // El filtro de mes se acota a la Pauta seleccionada. Sin Pauta
        // seleccionada se muestran únicamente los meses que existen dentro
        // de las Pautas disponibles para la dependencia seleccionada.
        $selectedPautaId = $request->filled('pauta_id') ? $request->integer('pauta_id') : null;
        $pautaFilterLoads = $selectedPautaId !== null
            ? $agencyFilterLoads->where('calendar_import_id', $selectedPautaId)->values()
            : $agencyFilterLoads;

        $months = $pautaFilterLoads
            ->pluck('effective_open_at')
            ->filter()
            ->map(fn ($date) => $date->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();

        $monthsByPauta = $agencyFilterLoads
            ->groupBy('calendar_import_id')
            ->map(fn ($pautaLoads) => $pautaLoads
                ->pluck('effective_open_at')
                ->filter()
                ->map(fn ($date) => $date->format('Y-m'))
                ->unique()
                ->sortDesc()
                ->values()
                ->all()
            )
            ->all();

        // Se conserva esta estructura por compatibilidad con URLs antiguas.
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
            'pautas',
            'months',
            'monthsByTemplate',
            'monthsByPauta',
            'filterUnits',
            'isDirectionLocked'
        ));
    }
}
