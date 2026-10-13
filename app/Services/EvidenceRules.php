<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceCorrectionWindow;
use App\Models\ScheduledLoadDeliverable;
use App\Models\User;
use App\Exceptions\WorkflowException as RuntimeException;

final class EvidenceRules
{
    public function __construct(private readonly AccessScopeService $access, private readonly CalendarAvailabilityService $availability) {}

    public function isEnabled(ScheduledLoadDeliverable $deliverable): bool
    {
        $load = $deliverable->scheduledLoad;
        if (!$load->agency?->active || $this->availability->isSuspended($load, now()) || $load->is_blocked || in_array($load->status->value, ['VALIDADO_Y_CERRADO', 'CANCELADA', 'SUSPENDIDA', 'REPROGRAMADA'], true)) {
            return false;
        }
        return $this->availability->isEnabled($load, now()) || $this->activeWindow($deliverable) !== null;
    }

    public function activeWindow(ScheduledLoadDeliverable $deliverable): ?EvidenceCorrectionWindow
    {
        return EvidenceCorrectionWindow::query()->where('deliverable_id', $deliverable->id)
            ->whereNull('used_at')->whereNull('revoked_at')
            ->where('opens_at', '<=', now())->where('closes_at', '>=', now())->latest('id')->first();
    }

    public function assertEditable(ScheduledLoadDeliverable $deliverable, User $user, ?Evidence $evidence = null): void
    {
        if (!$this->access->canUploadDeliverable($user, $deliverable)) {
            throw new RuntimeException('No tiene permiso para cargar este entregable.');
        }
        if (!$this->isEnabled($deliverable)) {
            throw new RuntimeException('La ventana de carga o corrección no está habilitada.');
        }
        if ($evidence && !in_array($evidence->status->value, ['EN_CAPTURA', 'CORREGIDO', 'OBSERVADO', 'RECHAZADO'], true)) {
            throw new RuntimeException('La evidencia enviada o validada está bloqueada; solicite una corrección autorizada.');
        }
    }

    public function fileErrors(Evidence $evidence): array
    {
        $requirement = $evidence->deliverable->templateRequirement;
        $files = $evidence->files()->where('version', $evidence->current_version)->get();
        $errors = [];
        if ($files->count() < max(1, (int) $requirement->min_files)) {
            $errors[] = 'Faltan archivos en la versión actual.';
        }
        if ($requirement->max_files !== null && $files->count() > $requirement->max_files) {
            $errors[] = 'La versión actual excede el máximo de archivos.';
        }
        foreach ($files as $file) {
            if (!in_array(strtolower($file->extension), $requirement->allowed_extensions ?? [], true)
                || $file->size_bytes > $requirement->max_size_mb * 1024 * 1024) {
                $errors[] = 'Hay archivos que incumplen formato o tamaño.';
            }
            if (in_array($file->antivirus_status, ['INFECTED', 'FAILED', 'ERROR'], true)
                || (config('siget.antivirus_enabled') && !in_array($file->antivirus_status, ['CLEAN', 'PASSED'], true))) {
                $errors[] = 'Hay archivos sin verificación antivirus satisfactoria.';
            }
        }
        return array_values(array_unique($errors));
    }

    public function invalidateInstitutionalValidation(\App\Models\ScheduledLoad $load): void
    {
        $load->institutionalReview()->update(['evidences_correct' => false, 'package_prepared_for_signature' => false, 'verified_at' => null, 'validated_at' => null]);
        $load->signedDocuments()->where('active', true)->update(['active' => false]);
        $load->update(['validated_at' => null, 'validated_by' => null]);
    }
}
