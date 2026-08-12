<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLoad;
use App\Services\AccessScopeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MyLoadsController extends Controller
{
    public function __invoke(Request $request, AccessScopeService $access): View
    {
        $user = $request->user();
        $unitIds = $access->accessibleUnitIds($user);

        $loads = $access->scopeLoads(
            ScheduledLoad::query()
                ->with([
                    'agency',
                    'template',
                    'deliverables' => function ($query) use ($access, $user, $unitIds) {
                        if ($unitIds !== []) {
                            $access->scopeDeliverables($query, $user);
                        }
                        $query->with([
                            'organizationalUnit',
                            'templateRequirement',
                            'evidences.files',
                        ]);
                    },
                ])
                ->orderByDesc('effective_open_at'),
            $user
        )->paginate(18)->withQueryString();

        return view('cargas.mis-cargas', compact('loads'));
    }
}
