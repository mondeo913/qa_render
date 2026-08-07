<?php

namespace App\Http\Controllers;

use App\Models\ContractingAgency;
use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IntelligenceController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardAnalyticsService $analytics
    ): View {
        abort_unless($request->user()->hasPermission('intelligence.view'), 403);

        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('intelligence.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'agencies' => ContractingAgency::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
        ]);
    }
}
