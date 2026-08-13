<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvidenceUploadRequest;
use App\Models\ScheduledLoadDeliverable;
use App\Services\EvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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

        try {
            $evidence = $service->upload(
                $deliverable,
                $request->file('file'),
                $request->user(),
                $request->string('title')->toString() ?: null
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Error inesperado al cargar evidencia.', [
                'user_id' => $request->user()?->id,
                'deliverable_id' => $deliverable->id,
                'exception' => $exception,
            ]);

            return back()
                ->withInput()
                ->with('error', 'No fue posible procesar la evidencia. Inténtelo nuevamente.');
        }

        return redirect()
            ->route('evidences.show', $evidence)
            ->with('success', 'Evidencia cargada correctamente.');
    }
}
