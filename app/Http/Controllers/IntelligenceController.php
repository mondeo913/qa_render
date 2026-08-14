<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Models\OrganizationalUnit;
use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function __invoke(Request $request, DashboardAnalyticsService $analytics): View
    {
        abort_unless($request->user()->hasPermission('intelligence.view'), 403);
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'organizational_unit_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $agencies = ContractingAgency::query()->where('active', true)->orderBy('name')->get();
        $units = OrganizationalUnit::query()->when(!empty($filters['agency_id']), fn($q)=>$q->where('contracting_agency_id',(int)$filters['agency_id']))->orderBy('name')->get();
        return view('intelligence.index', [
            'analytics'=>$analytics->forUser($request->user(),$filters),
            'agencies'=>$agencies,
            'units'=>$units,
            'filters'=>$filters,
        ]);
    }
}
