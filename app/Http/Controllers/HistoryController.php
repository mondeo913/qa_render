<?php

namespace App\Http\Controllers;

use App\Models\EvidenceReview;
use App\Models\LoadStatusHistory;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function __invoke(Request $request, AccessScopeService $access)
    {
        $loadIds = $access
            ->scopeLoads(\App\Models\ScheduledLoad::query(), $request->user())
            ->select('scheduled_loads.id');

        return view('history.index', [
            'statusHistory' => LoadStatusHistory::query()
                ->with(['scheduledLoad', 'user'])
                ->whereIn('scheduled_load_id', clone $loadIds)
                ->latest()
                ->paginate(25, ['*'], 'status_page'),
            'reviews' => EvidenceReview::query()
                ->with(['evidence.scheduledLoad'])
                ->whereHas('evidence', fn ($query) =>
                    $query->whereIn('scheduled_load_id', clone $loadIds)
                )
                ->latest()
                ->limit(25)
                ->get(),
        ]);
    }
}
