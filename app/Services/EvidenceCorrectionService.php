<?php

namespace App\Services;

use App\Models\EvidenceCorrectionWindow;
use App\Models\ScheduledLoad;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use App\Exceptions\WorkflowException as RuntimeException;

final class EvidenceCorrectionService
{
    public function __construct(private readonly AccessScopeService $access, private readonly EvidenceRules $rules,
        private readonly LoadStatusService $statuses, private readonly AuditService $audit, private readonly AlertService $alerts) {}

    public function authorize(ScheduledLoad $load, User $user, array $deliverableIds, CarbonInterface $opens, CarbonInterface $closes, string $reason, bool $reopen = false): void
    {
        $permission = $reopen ? 'scheduled_load.reopen' : 'scheduled_load.verify';
        if (!in_array($user->role?->code, ['ADMINISTRADOR', 'ENLACE_INSTITUCIONAL'], true)
            || !$user->hasPermission($permission) || !$this->access->canAccessLoad($user, $load)) {
            throw new RuntimeException('No puede autorizar correcciones de este expediente.');
        }
        if (blank($reason) || $closes->lte($opens) || $closes->lte(now()) || $deliverableIds === []) {
            throw new RuntimeException('Indique entregables, motivo y una ventana de corrección válida.');
        }
        DB::transaction(function () use ($load, $user, $deliverableIds, $opens, $closes, $reason, $reopen) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            if ($reopen !== ($load->status->value === 'VALIDADO_Y_CERRADO')
                || (!$reopen && ($load->is_blocked || in_array($load->status->value, ['CANCELADA', 'SUSPENDIDA', 'REPROGRAMADA'], true)))) {
                throw new RuntimeException('Use reapertura para expedientes cerrados; no se pueden corregir cargas bloqueadas.');
            }
            $ids = array_values(array_unique(array_map('intval', $deliverableIds)));
            $deliverables = $load->deliverables()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($deliverables->count() !== count($ids)) {
                throw new RuntimeException('Los entregables deben pertenecer al expediente.');
            }
            if ($reopen) {
                $closure = $load->closure;
                if (!$closure) {
                    throw new RuntimeException('No existe constancia de cierre para reabrir.');
                }
                // Immutable snapshot preserves prior manifest, signature and certificate on every cycle.
                $this->audit->record('scheduled_load.reopened', $load, ['closure' => $closure->toArray(), 'institutional_review' => $load->institutionalReview?->toArray(), 'accounting_notice' => $load->accountingNotice?->toArray()], ['reason' => $reason, 'authorized_by' => $user->id]);
                $closure->update(['reopened' => true, 'reopened_reason' => $reason, 'reopened_by' => $user->id, 'reopened_at' => now()]);
                $load->deliverables()->whereNotIn('id', $ids)->where('status', 'CERRADO')->update(['status' => 'VALIDADO']);
                $load->update(['is_blocked' => false, 'block_reason' => null, 'closed_at' => null, 'closed_by' => null, 'row_version' => $load->row_version + 1]);
            }
            $this->rules->invalidateInstitutionalValidation($load);
            foreach ($deliverables as $deliverable) {
                EvidenceCorrectionWindow::query()->where('deliverable_id', $deliverable->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $window = EvidenceCorrectionWindow::query()->create(['deliverable_id' => $deliverable->id, 'authorized_by' => $user->id,
                    'reason' => $reason, 'opens_at' => $opens, 'closes_at' => $closes]);
                $deliverable->update(['status' => 'OBSERVADO', 'validated_at' => null, 'validated_by' => null, 'observations' => $reason]);
                $deliverable->evidences()->update(['status' => 'OBSERVADO', 'validated_at' => null, 'validated_by' => null]);
                $this->audit->record('evidence.correction.authorized', $window, [], ['deliverable_id' => $deliverable->id, 'reason' => $reason, 'authorized_by' => $user->id]);
            }
            $this->statuses->transition($load, $reopen ? 'REABIERTA' : 'OBSERVADA', $user, $reason,
                ['deliverable_ids' => $ids, 'opens_at' => $opens->toIso8601String(), 'closes_at' => $closes->toIso8601String()]);
            app(LoadProgressService::class)->recalculate($load);
            DB::afterCommit(fn () => $this->alerts->notifyCorrection($load, $reason));
        });
    }
}
