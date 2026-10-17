<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledLoad;
use App\Services\{AccessScopeService, DashboardAnalyticsService};
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class QueryController extends Controller
{
    public function agencies(Request $request, AccessScopeService $access)
    {
        return response()->json(['data' => $access->availableAgencies($request->user())
            ->map(fn ($agency) => $agency->only(['id', 'code', 'name', 'legal_name']))->all()]);
    }

    public function loads(Request $request, AccessScopeService $access)
    {
        $data = $request->validate([
            'contracting_agency_id' => ['nullable', 'integer'],
            'calendar_import_id' => ['nullable', 'integer'],
            'status' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\ScheduledLoadStatus::class)],
            'start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $access->scopeLoads(ScheduledLoad::query()->with('agency'), $request->user());
        foreach (['contracting_agency_id', 'calendar_import_id', 'status'] as $key) {
            if (isset($data[$key])) $query->where($key, $data[$key]);
        }
        if (isset($data['start'])) $query->whereDate('effective_close_at', '>=', $data['start']);
        if (isset($data['end'])) $query->whereDate('effective_open_at', '<=', $data['end']);
        $page = $query->orderBy('effective_open_at')->orderBy('id')->paginate($data['per_page'] ?? 25);

        return response()->json(['data' => $page->getCollection()->map(fn ($load) => ApiData::load($load, $request->user()))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Request $request, ScheduledLoad $load, AccessScopeService $access)
    {
        abort_unless($access->canAccessLoad($request->user(), $load), 403);
        return response()->json(['data' => ApiData::load($load, $request->user(), true)]);
    }

    public function dashboard(Request $request, DashboardAnalyticsService $analytics)
    {
        $filters = $request->validate(['contracting_agency_id' => ['nullable', 'integer']]);
        $result = $analytics->forUser($request->user(), ['agency_id' => $filters['contracting_agency_id'] ?? null]);
        return response()->json(['data' => Arr::only($result, [
            'kpis', 'status_distribution', 'monthly_trend', 'unit_performance', 'evidence_summary', 'evidence_by_direction',
            'evidence_trend', 'deliverable_funnel', 'evidence_funnel',
        ])]);
    }

    public function calendar(Request $request, \App\Http\Controllers\CalendarController $calendar)
    {
        $request->validate(['start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after:start'], 'contracting_agency_id' => ['nullable', 'integer']]);
        abort_if($request->date('start')->diffInDays($request->date('end')) > 93, 422, 'Consulte un máximo de 93 días por solicitud.');
        return app()->call([$calendar, 'events'], ['request' => $request]);
    }
}
