<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IndicatorController extends Controller
{
    public function index(
        Request $request,
        DashboardAnalyticsService $analytics
    ): View {
        abort_unless($request->user()->hasPermission('indicators.view'), 403);

        return view('reportes.indicadores', [
            'analytics' => $analytics->forUser(
                $request->user(),
                $request->only(['status', 'from', 'to'])
            ),
        ]);
    }
}
