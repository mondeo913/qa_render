<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\CalendarAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ScheduledLoadController extends Controller
{
    public function show(
        Request $request,
        ScheduledLoad $load,
        AccessScopeService $access,
        CalendarAvailabilityService $availability
    ): View {
        abort_unless($access->canAccessLoad($request->user(), $load), 403);

        $load->load([
            'agency',
            'template.requirements',
            'deliverables.organizationalUnit',
            'deliverables.templateRequirement',
            'deliverables.evidences.files',
            'deliverables.evidences.reviews',
            'institutionalReview',
            'signedDocuments.files',
            'closure',
            'reviewAssignments.fiscalizador',
            'accountingNotice',
            'statusHistory.user',
        ]);

        $unitIds = $access->accessibleUnitIds($request->user());
        if ($unitIds !== []) {
            $load->setRelation(
                'deliverables',
                $load->deliverables
                    ->whereIn('organizational_unit_id', $unitIds)
                    ->values()
            );
        }

        return view('repositorio.carga', [
            'load' => $load,
            'uploadEnabled' => $availability->isEnabled($load, now()),
            'uploadTooltip' => $availability->tooltip($load),
            'fiscalizadores' => in_array(
                $request->user()->role?->code,
                ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'],
                true
            )
                ? User::query()
                    ->whereHas('role', fn ($role) =>
                        $role->where('code', 'FISCALIZADOR')
                    )
                    ->where('status', 'ACTIVE')
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }
}
