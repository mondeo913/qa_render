<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\EvidenceFile;
use App\Services\AccessScopeService;
use App\Services\EvidenceWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EvidenceWorkflowController extends Controller
{
    public function show(
        Request $request,
        Evidence $evidence,
        AccessScopeService $access
    ) {
        abort_unless($access->canAccessEvidence($request->user(), $evidence), 403);

        return view('evidencias.show', [
            'evidence' => $evidence->load([
                'scheduledLoad',
                'deliverable.templateRequirement',
                'deliverable.organizationalUnit',
                'files',
                'reviews',
            ]),
            'canReview' => $access->canReviewEvidence(
                $request->user(),
                $evidence
            ),
        ]);
    }

    public function submit(
        Request $request,
        Evidence $evidence,
        EvidenceWorkflowService $workflow
    ): RedirectResponse {
        $workflow->submit($evidence, $request->user());

        return redirect()
            ->route('loads.mine')
            ->with(
                'success',
                'Evidencia cerrada y enviada. La misma evidencia queda disponible, sin duplicar archivos, en el Repositorio de Revisión institucional y en el repositorio correspondiente a su Dirección.'
            );
    }

    public function review(
        Request $request,
        Evidence $evidence,
        EvidenceWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => [
                'required',
                Rule::in(['APROBADO', 'OBSERVADO', 'RECHAZADO']),
            ],
            'comments' => ['nullable', 'string', 'max:4000'],
        ]);

        $workflow->review(
            $evidence,
            $request->user(),
            $data['decision'],
            $data['comments'] ?? null
        );

        return back()->with('success', 'La revisión fue registrada.');
    }

    public function download(
        Request $request,
        EvidenceFile $file,
        AccessScopeService $access
    ) {
        $file->load('evidence.deliverable.scheduledLoad');
        abort_unless(
            $file->evidence
                && $access->canAccessEvidence($request->user(), $file->evidence),
            403
        );

        return Storage::disk($file->storage_disk)->download(
            $file->storage_path,
            $file->original_name
        );
    }
}
