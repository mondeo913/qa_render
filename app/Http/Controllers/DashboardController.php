<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Services\DashboardAnalyticsService;
use App\Support\RolePresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): View
    {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $agencies = ContractingAgency::query()->where('active', true)->orderBy('name')->get();
        $units = OrganizationalUnit::query()
            ->where('active', true)
            ->when(!empty($filters['agency_id']), fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id']))
            ->orderBy('contracting_agency_id')
            ->orderBy('name')
            ->get()
            ->unique(fn ($unit) => ($unit->contracting_agency_id ?? 0).'|'.mb_strtolower(trim($unit->name)))
            ->values();

        return view('dashboard.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            'presentation' => RolePresentation::for($request->user()->role?->code),
        ]);
    }
}
