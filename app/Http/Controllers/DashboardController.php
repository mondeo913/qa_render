<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use App\Support\RolePresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardAnalyticsService $analytics
    ): View {
        $filters = $request->validate([
            'agency_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('dashboard.index', [
            'analytics' => $analytics->forUser($request->user(), $filters),
            'filters' => $filters,
            'presentation' => RolePresentation::for($request->user()->role?->code),
        ]);
    }
}
