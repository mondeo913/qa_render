<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EvidenceUploadRequest;
use App\Models\{Evidence, ScheduledLoadDeliverable};
use App\Services\{AccessScopeService, EvidenceService, EvidenceWorkflowService};
use Illuminate\Http\Request;

final class EvidenceController extends Controller
{
    public function store(EvidenceUploadRequest $request, AccessScopeService $access, EvidenceService $service)
    {
        $deliverable = ScheduledLoadDeliverable::with(['scheduledLoad', 'templateRequirement', 'organizationalUnit'])->findOrFail($request->integer('deliverable_id'));
        abort_unless($access->canUploadDeliverable($request->user(), $deliverable), 403);
        $evidence = $service->upload($deliverable, $request->file('file'), $request->user(), $request->input('title'));
        return response()->json(['data' => ApiData::evidence($evidence->fresh())], 201);
    }

    public function show(Request $request, Evidence $evidence, AccessScopeService $access)
    {
        abort_unless($access->canAccessEvidence($request->user(), $evidence), 403);
        return response()->json(['data' => ApiData::evidence($evidence)]);
    }

    public function submit(Request $request, Evidence $evidence, AccessScopeService $access, EvidenceWorkflowService $service)
    {
        abort_unless($access->canAccessEvidence($request->user(), $evidence) && $request->user()->hasPermission('evidence.upload'), 403);
        $service->submit($evidence, $request->user());
        return response()->json(['data' => ApiData::evidence($evidence->fresh())]);
    }

    public function review(Request $request, Evidence $evidence, AccessScopeService $access, EvidenceWorkflowService $service)
    {
        abort_unless($access->canReviewEvidence($request->user(), $evidence), 403);
        $data = $request->validate(['decision' => ['required', 'in:APROBADO,OBSERVADO,RECHAZADO'], 'comments' => ['nullable', 'string', 'max:4000']]);
        $service->review($evidence, $request->user(), $data['decision'], $data['comments'] ?? null);
        return response()->json(['data' => ApiData::evidence($evidence->fresh())]);
    }
}
