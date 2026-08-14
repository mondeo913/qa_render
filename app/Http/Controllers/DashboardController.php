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
            'to' => ['nullable', 'date'],
        ]);

        $agencies = ContractingAgency::query()->where('active', true)->orderBy('name')->get();
        $units = OrganizationalUnit::query()
            ->when(!empty($filters['agency_id']), fn ($q) => $q->where('contracting_agency_id', (int) $filters['agency_id']))
            ->orderBy('name')->get();

        return view('dashboard.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'filters' => $filters,
            'agencies' => $agencies,
            'units' => $units,
            'presentation' => RolePresentation::for($request->user()->role?->code),
        ]);
    }
}
