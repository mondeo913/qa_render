<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use App\Services\CalendarAvailabilityService;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request, AccessScopeService $access): View
    {
        $upcoming = $access->scopeLoads(ScheduledLoad::query()->with('agency'), $request->user())
            ->where('effective_close_at', '>=', now())
            ->whereNotIn('status', ['VALIDADO_Y_CERRADO', 'CANCELADA'])
            ->orderBy('effective_open_at')
            ->limit(12)
            ->get();

        return view('calendario.index', [
            'agencies' => ContractingAgency::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'canImport' => $request->user()->hasPermission('calendar.import'),
            'upcoming' => $upcoming,
        ]);
    }

    public function events(
        Request $request,
        CalendarAvailabilityService $availability,
        AccessScopeService $access
    ): JsonResponse {
        $start = $request->date('start');
        $end = $request->date('end');

        $query = $access->scopeLoads(
            ScheduledLoad::query(),
            $request->user()
        );

        if ($request->filled('contracting_agency_id')) {
            $query->where(
                'contracting_agency_id',
                $request->integer('contracting_agency_id')
            );
        }

        $loads = $query
            ->where('effective_open_at', '<', $end)
            ->where('effective_close_at', '>=', $start)
            ->get();

        return response()->json($loads->map(
            fn (ScheduledLoad $load) => [
                'id' => $load->id,
                'title' => $load->title,
                'start' => $load->effective_open_at->toIso8601String(),
                'end' => $load->effective_close_at->toIso8601String(),
                'classNames' => [
                    'siget-event',
                    'status-'.strtolower(
                        $load->status instanceof \BackedEnum
                            ? $load->status->value
                            : $load->status
                    ),
                ],
                'extendedProps' => [
                    'status' => $load->status instanceof \BackedEnum
                        ? $load->status->value
                        : $load->status,
                    'trafficLight' => $load->traffic_light instanceof \BackedEnum
                        ? $load->traffic_light->value
                        : $load->traffic_light,
                    'completion' => $load->completion_percentage,
                    'enabled' => $availability->isEnabled($load, now()),
                    'tooltip' => $availability->tooltip($load),
                    'url' => route('loads.show', $load),
                ],
            ]
        ));
    }

    public function programmedDates(
        Request $request,
        AccessScopeService $access
    ): JsonResponse {
        $query = $access->scopeLoads(
            ScheduledLoad::query(),
            $request->user()
        );

        if ($request->filled('contracting_agency_id')) {
            $query->where(
                'contracting_agency_id',
                $request->integer('contracting_agency_id')
            );
        }

        $dates = $query
            ->where('effective_open_at', '<', $request->date('end'))
            ->where('effective_close_at', '>=', $request->date('start'))
            ->get()
            ->flatMap(fn ($load) => collect(CarbonPeriod::create(
                $load->effective_open_at->startOfDay(),
                $load->effective_close_at->startOfDay()
            ))->map->toDateString())
            ->unique()
            ->values();

        return response()->json(['dates' => $dates]);
    }
}
