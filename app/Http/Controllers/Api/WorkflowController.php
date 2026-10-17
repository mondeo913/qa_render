<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{ClosureChecklistRequest, SignedDocumentRequest};
use App\Models\ScheduledLoad;
use App\Services\{AccessScopeService, EvidenceCorrectionService, InstitutionalClosureService, ReschedulingService};
use Illuminate\Http\Request;

final class WorkflowController extends Controller
{
    public function checklist(ClosureChecklistRequest $request, ScheduledLoad $load, InstitutionalClosureService $service)
    {
        $this->authorize('verify', $load);
        $service->updateChecklist($load, $request->user()->id, $request->boolean('evidences_correct'),
            $request->boolean('package_prepared_for_signature'), $request->input('observations'));
        return $this->result($request, $load);
    }

    public function signedDocument(SignedDocumentRequest $request, ScheduledLoad $load, InstitutionalClosureService $service)
    {
        $this->authorize('uploadSigned', $load);
        $service->uploadSignedDocument($load, $request->file('file'), $request->user()->id, $request->safe()->except('file'));
        return $this->result($request, $load, 201);
    }

    public function close(Request $request, ScheduledLoad $load, InstitutionalClosureService $service)
    {
        $this->authorize('close', $load);
        $request->validate(['closing_comment' => ['nullable', 'string', 'max:4000']]);
        $service->close($load, $request->user()->id, $request->input('closing_comment', ''));
        return $this->result($request, $load);
    }

    public function corrections(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service)
    {
        $this->authorize('verify', $load);
        return $this->correction($request, $load, $service, false);
    }

    public function reopen(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service)
    {
        $this->authorize('reopen', $load);
        return $this->correction($request, $load, $service, true);
    }

    private function correction(Request $request, ScheduledLoad $load, EvidenceCorrectionService $service, bool $reopen)
    {
        $data = $request->validate(['deliverable_ids' => ['required', 'array', 'min:1'],
            'deliverable_ids.*' => ['required', 'integer', 'distinct'], 'reason' => ['required', 'string', 'min:10', 'max:4000'],
            'opens_at' => ['required', 'date'], 'closes_at' => ['required', 'date', 'after:opens_at', 'after:now']]);
        $service->authorize($load, $request->user(), $data['deliverable_ids'], $request->date('opens_at'),
            $request->date('closes_at'), $data['reason'], $reopen);
        return $this->result($request, $load);
    }

    public function reschedule(Request $request, ScheduledLoad $load, ReschedulingService $service, AccessScopeService $access)
    {
        abort_unless($request->user()->hasPermission('calendar.reschedule') && $access->canManageAgency($request->user(), $load->contracting_agency_id), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:4000'],
            'opens_at' => ['required', 'date'], 'closes_at' => ['required', 'date', 'after:opens_at', 'after:now']]);
        $service->reschedule($load, $request->user(), $request->date('opens_at'), $request->date('closes_at'), $data['reason']);
        return $this->result($request, $load);
    }

    public function assign(Request $request, ScheduledLoad $load, \App\Http\Controllers\ReviewAssignmentController $assignments)
    {
        // Run the same web authorization/validation; return only the scoped API resource.
        app()->call([$assignments, 'store'], ['request' => $request, 'load' => $load]);
        return $this->result($request, $load);
    }

    private function result(Request $request, ScheduledLoad $load, int $status = 200)
    {
        return response()->json(['data' => ApiData::load($load->fresh(), $request->user(), true)], $status);
    }
}
