<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index(Request $request, AccessScopeService $access): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:60'],
            'agency_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $unitIds = $access->accessibleUnitIds($user);
        $base = $access->scopeLoads(ScheduledLoad::query(), $user);
        $accessibleIds = (clone $base)->pluck('id');
        $agencyCounts = (clone $base)
            ->selectRaw('contracting_agency_id, COUNT(*) AS total')
            ->groupBy('contracting_agency_id')
            ->pluck('total', 'contracting_agency_id');
        $agencies = (clone $base)
            ->with('agency')
            ->get()
            ->pluck('agency')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $query = ScheduledLoad::query()
            ->with([
                'agency',
                'deliverables' => function ($d) use ($access, $user, $unitIds) {
                    if ($unitIds !== []) {
                        $access->scopeDeliverables($d, $user);
                    }
                    $d->with(['organizationalUnit', 'evidences.files']);
                },
            ])
            ->whereIn('id', $accessibleIds);

        if (!empty($filters['agency_id'])) {
            $query->where('contracting_agency_id', (int) $filters['agency_id']);
        }
        if (!empty($filters['q'])) {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn ($b) =>
                $b->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(period_label) LIKE ?', [$term])
                    ->orWhereHas('agency', fn ($a) =>
                        $a->whereRaw('LOWER(name) LIKE ?', [$term])
                    )
            );
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('effective_open_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('effective_open_at', '<=', $filters['to']);
        }

        $loads = $query->orderByDesc('effective_open_at')->paginate(24)->withQueryString();

        // Los archivos recientes también deben respetar la dirección del operador;
        // no basta con filtrar únicamente por la carga, porque una misma carga puede
        // contener entregables de varias direcciones.
        $recentFilesQuery = EvidenceFile::query()
            ->with(['evidence.scheduledLoad.agency', 'evidence.deliverable.organizationalUnit'])
            ->whereHas('evidence', function ($e) use ($accessibleIds, $access, $user, $unitIds) {
                $e->whereIn('scheduled_load_id', $accessibleIds);
                $e->whereHas('deliverable', function ($d) use ($access, $user, $unitIds) {
                    if ($unitIds !== []) {
                        $access->scopeDeliverables($d, $user);
                    }
                });
            });

        $recentFiles = (clone $recentFilesQuery)
            ->latest()
            ->limit(12)
            ->get();

        $usedBytes = (clone $recentFilesQuery)->sum('size_bytes');

        return view(
            'repositorio.index',
            compact('loads', 'filters', 'agencies', 'agencyCounts', 'recentFiles', 'usedBytes')
        );
    }
}
