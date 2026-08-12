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

        // Institutional repository hierarchy:
        // DEPENDENCY -> PROGRAMMED MONTH -> CONTRACTED PAUTA -> DIRECTIONS -> EVIDENCES.
        // Evidence remains traceable to its deliverable/direction, but the
        // institutional review/reporting unit is the monthly pauta expediente.
        $dependencyLoads = (clone $base)
            ->with([
                'agency.units',
                'template',
                'deliverables.organizationalUnit',
                'deliverables.templateRequirement',
                'deliverables.evidences.files',
            ])
            ->get();

        $dependencySummaries = $dependencyLoads
            ->groupBy('contracting_agency_id')
            ->map(function ($loads) {
                $agency = $loads->first()?->agency;
                $deliverables = $loads->flatMap->deliverables;
                $groups = $loads->groupBy(function ($load) {
                    $month = $load->effective_open_at?->format('Y-m')
                        ?? ($load->period_label ?: 'sin-periodo');
                    return $month.'|'.($load->template_id ?? 'sin-pauta');
                })->map(function ($group) {
                    $first = $group->first();
                    $evidences = $group->flatMap->deliverables->flatMap->evidences;
                    $directions = $group->flatMap->deliverables->pluck('organizationalUnit')
                        ->filter()->unique('id')->sortBy('name')->values();
                    return (object) [
                        'loads' => $group->sortByDesc('effective_open_at')->values(),
                        'month' => $first?->effective_open_at?->format('Y-m') ?? $first?->period_label,
                        'month_label' => $first?->effective_open_at?->locale('es')->translatedFormat('F Y') ?? $first?->period_label ?? 'Sin periodo',
                        'template' => $first?->template,
                        'template_id' => $first?->template_id,
                        'load_count' => $group->count(),
                        'directions' => $directions,
                        'deliverables' => $group->flatMap->deliverables,
                        'evidences' => $evidences,
                        'evidence_count' => $evidences->count(),
                        'file_count' => $evidences->sum(fn ($e) => $e->files->count()),
                        'required_count' => $group->flatMap->deliverables->filter(fn ($d) => $d->templateRequirement?->required)->count(),
                        'first_load' => $first,
                    ];
                })->sortByDesc('month')->values();

                return (object) [
                    'agency' => $agency,
                    'loads' => $loads->sortByDesc('effective_open_at')->values(),
                    'load_count' => $loads->count(),
                    'directions' => $deliverables->pluck('organizationalUnit')->filter()->unique('id')->sortBy('name')->values(),
                    'evidence_count' => $deliverables->sum(fn ($d) => $d->evidences->count()),
                    'file_count' => $deliverables->sum(fn ($d) => $d->evidences->sum(fn ($e) => $e->files->count())),
                    'required_count' => $deliverables->filter(fn ($d) => $d->templateRequirement?->required)->count(),
                    'monthlyPautas' => $groups,
                ];
            })
            ->sortBy(fn ($summary) => $summary->agency?->name ?? '')
            ->values();

        $agencyCounts = $dependencySummaries->mapWithKeys(fn ($summary) => [
            $summary->agency->id => $summary->load_count,
        ]);
        $agencies = $dependencySummaries->pluck('agency')->filter()->values();

        $query = ScheduledLoad::query()
            ->with([
                'agency', 'template',
                'deliverables' => function ($d) use ($access, $user, $unitIds) {
                    if ($unitIds !== []) {
                        $access->scopeDeliverables($d, $user);
                    }
                    $d->with(['organizationalUnit', 'templateRequirement', 'evidences.files']);
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
                    ->orWhereHas('agency', fn ($a) => $a->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('template', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term]))
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

        $recentFiles = (clone $recentFilesQuery)->latest()->limit(12)->get();
        $usedBytes = (clone $recentFilesQuery)->sum('size_bytes');

        return view('repositorio.index', compact(
            'loads', 'filters', 'agencies', 'agencyCounts', 'recentFiles', 'usedBytes', 'dependencySummaries'
        ));
    }
}
