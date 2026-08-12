<?php

namespace App\Http\Controllers;

use App\Models\ScheduledLoad;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\CalendarAvailabilityService;
use App\Services\InstitutionalClosureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ScheduledLoadController extends Controller
{
    public function show(
        Request $request,
        ScheduledLoad $load,
        AccessScopeService $access,
        CalendarAvailabilityService $availability,
        InstitutionalClosureService $closureService
    ): View {
        abort_unless($access->canAccessLoad($request->user(), $load), 403);

        $load->load([
            'agency.units',
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

        // The institutional link sees one complete expediente for the dependency,
        // with all directions/evidences participating in the pauta validation.
        $expedienteValidation = in_array(
            $request->user()->role?->code,
            ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'],
            true
        ) ? $closureService->validateExpediente($load) : [
            'ready' => false,
            'errors' => [],
            'required_deliverables' => 0,
            'directions' => 0,
        ];

        return view('repositorio.carga', [
            'load' => $load,
            'uploadEnabled' => $availability->isEnabled($load, now()),
            'uploadTooltip' => $availability->tooltip($load),
            'expedienteValidation' => $expedienteValidation,
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
