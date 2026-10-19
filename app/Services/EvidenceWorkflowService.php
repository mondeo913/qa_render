<?php

namespace App\Services;

use App\Enums\RoleCode;
use App\Models\Evidence;
use App\Models\EvidenceReview;
use App\Models\ScheduledLoad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Exceptions\WorkflowException as RuntimeException;

final class EvidenceWorkflowService
{
    public function __construct(
        private readonly AccessScopeService $access,
        private readonly AuditService $audit,
        private readonly LoadProgressService $progress,
        private readonly LoadStatusService $loadStatus,
        private readonly EvidenceRules $rules,
        private readonly AlertService $alerts
    ) {}

    public function submit(Evidence $evidence, User $user): Evidence
    {
        return DB::transaction(function () use ($evidence, $user) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($evidence->scheduled_load_id);
            $evidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            $deliverable = $evidence->deliverable;
            $deliverable->setRelation('scheduledLoad', $load);
            $this->rules->assertEditable($deliverable, $user, $evidence);
            if (!in_array($evidence->status->value, ['EN_CAPTURA', 'CORREGIDO'], true)) {
                throw new RuntimeException('Debe adjuntar una nueva versión antes de reenviar una evidencia observada.');
            }
            if ($errors = $this->rules->fileErrors($evidence)) {
                throw new RuntimeException(implode(' ', $errors));
            }
            $window = $this->rules->activeWindow($deliverable);
            $operationalAt = $load->operationalTimestamp();
            $evidence->update(['status' => 'ENVIADO', 'submitted_at' => $operationalAt, 'submitted_by' => $user->id]);
            $deliverable->update(['status' => 'ENVIADO', 'submitted_at' => $operationalAt, 'validated_at' => null, 'validated_by' => null]);
            $window?->update(['used_at' => now(), 'evidence_version' => $evidence->current_version]);
            $this->rules->invalidateInstitutionalValidation($load);
            $this->loadStatus->transition($load, $this->aggregateStatus($load), $user, 'Evidencia enviada para validación de su Dirección.', ['organizational_unit_id' => $deliverable->organizational_unit_id, 'evidence_id' => $evidence->id]);
            $this->progress->recalculate($load);
            $this->audit->record('evidence.submitted', $evidence, [], ['version' => $evidence->current_version, 'correction_window_id' => $window?->id]);
            DB::afterCommit(fn () => $this->alerts->notifyEvidence($evidence, 'Evidencia enviada a revisión'));
            return $evidence->fresh();
        });
    }

    public function review(Evidence $evidence, User $reviewer, string $decision, ?string $comments): Evidence
    {
        if (!$this->access->canReviewEvidence($reviewer, $evidence)) {
            throw new RuntimeException('No tiene permiso para revisar esta evidencia de la Dirección.');
        }
        $decision = strtoupper($decision);
        if (!in_array($decision, ['APROBADO', 'OBSERVADO', 'RECHAZADO'], true)) {
            throw new RuntimeException('La decisión de revisión no es válida.');
        }
        if ($decision !== 'APROBADO' && blank($comments)) {
            throw new RuntimeException('Debe indicar el motivo de la observación o rechazo.');
        }

        return DB::transaction(function () use ($evidence, $reviewer, $decision, $comments) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($evidence->scheduled_load_id);
            $evidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            $role = $reviewer->role?->code;
            $fiscalization = $role === 'FISCALIZADOR';
            if ((!$load->agency?->active && !$fiscalization) || $load->is_blocked || in_array($load->status->value, ['CANCELADA', 'SUSPENDIDA', 'REPROGRAMADA'], true)) {
                // A fiscalizador may audit a closed expediente without modifying it.
                if (!$fiscalization || $load->status->value !== 'VALIDADO_Y_CERRADO') {
                    throw new RuntimeException('El expediente está bloqueado para revisión.');
                }
            }
            if ($reviewer->canonicalIdentityId() === (User::find($evidence->submitted_by)?->canonicalIdentityId() ?? $evidence->submitted_by)) {
                throw new RuntimeException('No puede aprobar ni revisar su propia entrega.');
            }
            $type = $fiscalization ? 'FISCALIZATION' : (RoleCode::isDirectionDirector($role) ? 'DIRECTION' : 'INSTITUTIONAL');
            if (!$fiscalization) {
                $expected = $type === 'DIRECTION' ? ['ENVIADO'] : ['EN_REVISION'];
                if (!in_array($evidence->status->value, $expected, true)) {
                    throw new RuntimeException('La evidencia no está pendiente de esta etapa de revisión.');
                }
                if ($errors = $this->rules->fileErrors($evidence)) {
                    throw new RuntimeException(implode(' ', $errors));
                }
                if ($type === 'INSTITUTIONAL' && !$this->hasDirectionApproval($evidence)) {
                    throw new RuntimeException('Falta aprobación de la Dirección para la versión actual.');
                }
            } elseif (!in_array($evidence->status->value, ['ENVIADO', 'EN_REVISION', 'VALIDADO', 'CERRADO'], true)) {
                throw new RuntimeException('La evidencia aún no ha sido enviada para fiscalización.');
            }

            EvidenceReview::query()->create([
                'evidence_id' => $evidence->id, 'reviewer_id' => $reviewer->id,
                'decision' => $decision, 'comments' => $comments, 'review_type' => $type,
                'evidence_version' => $evidence->current_version,
            ]);
            if (!$fiscalization) {
                $status = $decision === 'APROBADO'
                    ? ($type === 'DIRECTION' ? 'EN_REVISION' : 'VALIDADO')
                    : ($decision === 'OBSERVADO' ? 'OBSERVADO' : 'RECHAZADO');
                $validated = $status === 'VALIDADO';
                $operationalAt = $load->operationalTimestamp();
                $evidence->update(['status' => $status, 'validated_at' => $validated ? $operationalAt : null, 'validated_by' => $validated ? $reviewer->id : null]);
                $evidence->deliverable->update(['status' => $status, 'validated_at' => $validated ? $operationalAt : null,
                    'validated_by' => $validated ? $reviewer->id : null, 'observations' => $comments]);
                $this->rules->invalidateInstitutionalValidation($load);
                $this->loadStatus->transition($load, $this->aggregateStatus($load), $reviewer, $comments ?: 'Revisión '.$type.' registrada.', ['organizational_unit_id' => $evidence->deliverable->organizational_unit_id, 'evidence_id' => $evidence->id]);
                $this->progress->recalculate($load);
            }
            $this->audit->record('evidence.reviewed', $evidence, [], ['decision' => $decision, 'reviewer_id' => $reviewer->id,
                'review_type' => $type, 'version' => $evidence->current_version]);
            DB::afterCommit(fn () => $this->alerts->notifyEvidence($evidence, 'Revisión '.$type.': '.$decision));
            return $evidence->fresh(['reviews', 'deliverable']);
        });
    }

    public function hasDirectionApproval(Evidence $evidence): bool
    {
        return $evidence->reviews()->where('evidence_version', $evidence->current_version)
            ->where('review_type', 'DIRECTION')->latest('id')->value('decision') === 'APROBADO';
    }

    private function aggregateStatus(ScheduledLoad $load): string
    {
        $statuses = $load->deliverables()->get()->filter(fn ($d) => $d->templateRequirement?->required)
            ->map(fn ($d) => $d->status->value);
        if ($statuses->contains(fn ($s) => in_array($s, ['OBSERVADO', 'RECHAZADO'], true))) {
            return 'OBSERVADA';
        }
        if ($statuses->isNotEmpty() && $statuses->every(fn ($s) => in_array($s, ['VALIDADO', 'CERRADO'], true))) {
            return 'VALIDADA';
        }
        if ($statuses->isNotEmpty() && $statuses->every(fn ($s) => in_array($s, ['EN_REVISION', 'VALIDADO', 'CERRADO'], true))) {
            return 'EN_REVISION_INSTITUCIONAL';
        }
        if ($statuses->isNotEmpty() && $statuses->every(fn ($s) => in_array($s, ['ENVIADO', 'EN_REVISION', 'VALIDADO', 'CERRADO'], true))) {
            return 'ENTREGADA';
        }
        return 'PARCIALMENTE_ENTREGADA';
    }
}
