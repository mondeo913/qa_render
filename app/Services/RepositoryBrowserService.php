<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\ScheduledLoad;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RepositoryBrowserService
{
    public function __construct(private readonly AccessScopeService $access) {}

    public function forUser(User $user, array $filters): array
    {
        $agencies = $this->access->availableAgencies($user);
        if (!empty($filters['agency_id'])) {
            abort_unless($agencies->contains('id', (int) $filters['agency_id']), 403);
        }

        // The folder catalog exists before a pauta is imported. Its contents remain scoped independently.
        $base = $this->access->scopeLoads(ScheduledLoad::query(), $user);
        $this->applyFilters($base, $filters);
        $agencyCounts = (clone $base)->select('scheduled_loads.contracting_agency_id')
            ->selectRaw('COUNT(*) AS total')->groupBy('scheduled_loads.contracting_agency_id')
            ->pluck('total', 'contracting_agency_id');
        $contentQuery = clone $base;
        if (!empty($filters['agency_id'])) {
            $contentQuery->where('scheduled_loads.contracting_agency_id', (int) $filters['agency_id']);
        }
        $contentQuery->with([
            'agency', 'template', 'calendarImport',
            'deliverables' => function ($query) use ($user) {
                $this->access->scopeDeliverables($query, $user);
                $query->with(['organizationalUnit', 'templateRequirement', 'evidences.files']);
            },
        ]);
        $visibleLoads = (clone $contentQuery)->orderByDesc('effective_open_at')->get();
        $loadsByAgency = $visibleLoads->groupBy('contracting_agency_id');
        $term = mb_strtolower(trim($filters['q'] ?? ''));
        $dependencySummaries = $agencies
            ->filter(fn ($agency) => empty($filters['agency_id']) || $agency->id === (int) $filters['agency_id'])
            ->filter(fn ($agency) => !empty($filters['agency_id']) || $term === ''
                || str_contains(mb_strtolower($agency->name.' '.$agency->code), $term)
                || $loadsByAgency->has($agency->id))
            ->map(fn ($agency) => $this->summarize($agency, $loadsByAgency->get($agency->id, collect())))
            ->values();

        $recentFilesQuery = EvidenceFile::query()
            ->whereHas('evidence', function ($query) use ($contentQuery, $user) {
                $query->whereIn('scheduled_load_id', (clone $contentQuery)->select('scheduled_loads.id'))
                    ->whereHas('deliverable', fn ($d) => $this->access->scopeDeliverables($d, $user));
            });
        $recentFiles = (clone $recentFilesQuery)
            ->with(['evidence.scheduledLoad.agency', 'evidence.deliverable.organizationalUnit'])
            ->latest()->limit(12)->get();
        $usedBytes = (clone $recentFilesQuery)->sum('size_bytes');
        $loads = (clone $contentQuery)->orderByDesc('effective_open_at')->paginate(24)->withQueryString();

        return compact('loads', 'agencies', 'agencyCounts', 'dependencySummaries', 'recentFiles', 'usedBytes', 'filters');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['pauta_id'])) $query->where('scheduled_loads.calendar_import_id', (int) $filters['pauta_id']);
        if (!empty($filters['status'])) $query->where('scheduled_loads.status', $filters['status']);
        if (!empty($filters['from'])) $query->whereDate('scheduled_loads.effective_open_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $query->whereDate('scheduled_loads.effective_open_at', '<=', $filters['to']);
        if (trim((string)($filters['q'] ?? '')) !== '') {
            $text = trim($filters['q']);
            $term = '%'.mb_strtolower($text).'%';
            $query->where(function ($search) use ($term, $text) {
                $search->whereRaw('LOWER(scheduled_loads.title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(scheduled_loads.period_label) LIKE ?', [$term])
                    ->orWhereHas('agency', fn ($a) => $a->where(fn ($name) => $name->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(code) LIKE ?', [$term])))
                    ->orWhereHas('calendarImport', fn ($p) => $p->whereRaw('LOWER(original_filename) LIKE ?', [$term]))
                    ->orWhereHas('template', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term]));
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $text)) {
                    $month = CarbonImmutable::createFromFormat('!Y-m', $text);
                    $search->orWhereBetween('scheduled_loads.effective_open_at', [$month, $month->endOfMonth()]);
                }
            });
        }
    }

    private function summarize($agency, Collection $loads): object
    {
        $deliverables = $loads->flatMap->deliverables;
        $groups = $loads->groupBy(function ($load) {
            $month = $load->effective_open_at?->format('Y-m') ?? ($load->period_label ?: 'sin-periodo');
            return $month.'|'.($load->calendar_import_id ?? 'template-'.$load->template_id);
        })->map(function ($group) {
            $first = $group->first();
            $items = $group->flatMap->deliverables;
            $evidences = $items->flatMap->evidences;
            return (object) [
                'loads' => $group->sortBy('effective_open_at')->values(),
                'month' => $first->effective_open_at?->format('Y-m') ?? $first->period_label,
                'month_label' => $first->effective_open_at?->locale('es')->translatedFormat('F Y') ?? $first->period_label ?? 'Sin periodo',
                'pauta_id' => $first->calendar_import_id,
                'pauta_name' => $first->calendarImport?->original_filename ?? $first->template?->name ?? $first->title,
                'load_count' => $group->count(),
                'directions' => $items->pluck('organizationalUnit')->filter()->unique('id')->sortBy('name')->values(),
                'deliverables' => $items,
                'evidence_count' => $evidences->count(),
                'file_count' => $evidences->sum(fn ($e) => $e->files->count()),
                'required_count' => $items->filter(fn ($d) => $d->templateRequirement?->required)->count(),
            ];
        })->sortByDesc('month')->values();

        return (object) [
            'agency' => $agency,
            'load_count' => $loads->count(),
            'directions' => $deliverables->pluck('organizationalUnit')->filter()->unique('id')->values(),
            'evidence_count' => $deliverables->sum(fn ($d) => $d->evidences->count()),
            'file_count' => $deliverables->sum(fn ($d) => $d->evidences->sum(fn ($e) => $e->files->count())),
            'monthlyPautas' => $groups,
        ];
    }
}
