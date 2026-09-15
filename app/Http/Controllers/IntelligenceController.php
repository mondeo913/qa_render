<?php

namespace App\Http\Controllers;

use App\Enums\RoleCode;
use App\Enums\ScheduledLoadStatus;
use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardAnalyticsService $analytics,
        AccessScopeService $access
    ): View {
        abort_unless($request->user()->hasPermission('intelligence.view'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
        ]);

        $agencies = ContractingAgency::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->when(
                !empty($filters['agency_id']),
                fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id'])
            )
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

        $periodQuery = $access->scopeLoads(
            ScheduledLoad::query(),
            $request->user()
        );

        if (!empty($filters['agency_id'])) {
            $periodQuery->where('contracting_agency_id', (int) $filters['agency_id']);
        }

        $unitIds = collect(explode(',', (string) ($filters['organizational_unit_id'] ?? '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($unitIds) {
            $periodQuery->whereHas(
                'deliverables',
                fn ($deliverables) => $deliverables->whereIn('organizational_unit_id', $unitIds)
            );
        }

        $periodDates = $periodQuery
            ->whereNotNull('effective_open_at')
            ->whereNotNull('effective_close_at')
            ->select(['effective_open_at', 'effective_close_at'])
            ->get();

        $periodMin = $periodDates->isEmpty()
            ? null
            : $periodDates->min('effective_open_at')->copy()->startOfMonth()->format('Y-m');
        $periodMax = $periodDates->isEmpty()
            ? null
            : $periodDates->max('effective_close_at')->copy()->startOfMonth()->format('Y-m');

        // Inteligencia utiliza los mismos estados ejecutivos que los demás menús de seguimiento.
        $visibleStatuses = [
            ScheduledLoadStatus::PROGRAMADA->value,
            ScheduledLoadStatus::REPROGRAMADA->value,
            ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
            ScheduledLoadStatus::VENCIDA->value,
        ];

        $role = $request->user()->role?->code;
        $statuses = collect($visibleStatuses)
            ->map(fn (string $status) => [
                'code' => $status,
                'label' => $this->statusLabel($status),
            ])
            ->when(
                RoleCode::isDirectionDirector($role),
                fn ($collection) => $collection->filter(
                    fn (array $status) => in_array($status['code'], $visibleStatuses, true)
                )
            )
            ->values();

        return view('intelligence.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'agencies' => $agencies,
            'units' => $units,
            'filterAgencies' => $agencies,
            'filterUnits' => $units,
            'filters' => $filters,
            'statuses' => $statuses,
            'periodMin' => $periodMin,
            'periodMax' => $periodMax,
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'PROGRAMADA' => 'Programado',
            'REPROGRAMADA' => 'Reprogramado',
            'VALIDADO_Y_CERRADO' => 'Validado y cerrado',
            'VENCIDA' => 'Vencido',
            default => str($status)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }
}
