<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvidenceUploadRequest;
use App\Models\ScheduledLoadDeliverable;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;

class EvidenceController extends Controller
{
    public function store(
        EvidenceUploadRequest $request,
        EvidenceService $service
    ): RedirectResponse {
        $deliverable = ScheduledLoadDeliverable::query()
            ->with([
                'scheduledLoad',
                'templateRequirement',
                'organizationalUnit',
            ])
            ->findOrFail($request->integer('deliverable_id'));

        $evidence = $service->upload(
            $deliverable,
            $request->file('file'),
            $request->user(),
            $request->string('title')->toString() ?: null
        );

        return redirect()
            ->route('evidences.show', $evidence)
            ->with('success', 'Evidencia cargada correctamente.');
    }
}
