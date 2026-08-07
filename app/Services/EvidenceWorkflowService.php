<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EvidenceWorkflowService
{
    public function __construct(
        private readonly AccessScopeService $access,
        private readonly AuditService $audit,
        private readonly LoadProgressService $progress,
        private readonly LoadStatusService $loadStatus
    ) {}

    public function submit(Evidence $evidence, User $user): Evidence
    {
        if (!$this->access->canAccessEvidence($user, $evidence)) {
            throw new RuntimeException('No tiene acceso a esta evidencia.');
        }

        return DB::transaction(function () use ($evidence, $user) {
            $evidence->load(['files', 'deliverable.templateRequirement']);

            $currentVersionFiles = $evidence->files
                ->where('version', $evidence->current_version);

            $minimum = $evidence->deliverable->templateRequirement->min_files;

            if ($currentVersionFiles->count() < $minimum) {
                throw new RuntimeException(
                    "Debe adjuntar al menos {$minimum} archivo(s) en la versión actual."
                );
            }

            $evidence->update([
                'status' => 'ENVIADO',
                'submitted_at' => now(),
                'submitted_by' => $user->id,
            ]);

            $evidence->deliverable->update([
                'status' => 'ENVIADO',
                'submitted_at' => now(),
            ]);

            $load = $evidence->scheduledLoad;
            $this->loadStatus->transition(
                $load,
                'PARCIALMENTE_ENTREGADA',
                $user,
                'Se envió una evidencia para revisión.'
            );

            $this->progress->recalculate($load);
            $this->audit->record('evidence.submitted', $evidence);

            return $evidence->fresh();
        });
    }

    public function review(
        Evidence $evidence,
        User $reviewer,
        string $decision,
        ?string $comments
    ): Evidence {
        if (!$this->access->canReviewEvidence($reviewer, $evidence)) {
            throw new RuntimeException('No tiene permiso para revisar esta evidencia.');
        }

        $decision = strtoupper($decision);

        if (!in_array($decision, ['APROBADO', 'OBSERVADO', 'RECHAZADO'], true)) {
            throw new RuntimeException('La decisión de revisión no es válida.');
        }

        return DB::transaction(function () use (
            $evidence,
            $reviewer,
            $decision,
            $comments
        ) {
            EvidenceReview::query()->create([
                'evidence_id' => $evidence->id,
                'reviewer_id' => $reviewer->id,
                'decision' => $decision,
                'comments' => $comments,
                'review_type' => $reviewer->role?->code === 'FISCALIZADOR'
                    ? 'FISCALIZATION'
                    : 'INSTITUTIONAL',
            ]);

            $statusMap = [
                'APROBADO' => 'VALIDADO',
                'OBSERVADO' => 'OBSERVADO',
                'RECHAZADO' => 'RECHAZADO',
            ];

            $deliverableMap = [
                'APROBADO' => 'VALIDADO',
                'OBSERVADO' => 'OBSERVADO',
                'RECHAZADO' => 'OBSERVADO',
            ];

            $evidence->update([
                'status' => $statusMap[$decision],
                'validated_at' => $decision === 'APROBADO' ? now() : null,
                'validated_by' => $decision === 'APROBADO' ? $reviewer->id : null,
            ]);

            $evidence->deliverable->update([
                'status' => $deliverableMap[$decision],
                'validated_at' => $decision === 'APROBADO' ? now() : null,
                'validated_by' => $decision === 'APROBADO' ? $reviewer->id : null,
                'observations' => $comments,
            ]);

            $load = $evidence->scheduledLoad->fresh('deliverables');
            $allValidated = $load->deliverables->every(
                fn ($item) => in_array(
                    $item->status instanceof \BackedEnum
                        ? $item->status->value
                        : (string) $item->status,
                    ['VALIDADO', 'CERRADO'],
                    true
                )
            );

            if ($decision === 'OBSERVADO' || $decision === 'RECHAZADO') {
                $this->loadStatus->transition(
                    $load,
                    'OBSERVADA',
                    $reviewer,
                    $comments
                );
            } elseif ($allValidated) {
                $this->loadStatus->transition(
                    $load,
                    'EN_REVISION_INSTITUCIONAL',
                    $reviewer,
                    'Todos los entregables fueron validados.'
                );
            } else {
                $this->loadStatus->transition(
                    $load,
                    'PARCIALMENTE_ENTREGADA',
                    $reviewer,
                    'Una evidencia fue validada.'
                );
            }

            $this->progress->recalculate($load);
            $this->audit->record(
                'evidence.reviewed',
                $evidence,
                [],
                [
                    'decision' => $decision,
                    'reviewer_id' => $reviewer->id,
                ]
            );

            return $evidence->fresh(['reviews', 'deliverable']);
        });
    }
}
