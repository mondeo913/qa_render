<?php

namespace App\Services;

use App\Models\LoadStatusHistory;
use App\Models\ScheduledLoad;
use App\Models\User;

final class LoadStatusService
{
    public function transition(
        ScheduledLoad $load,
        string $newStatus,
        ?User $user,
        ?string $reason = null,
        array $metadata = []
    ): void {
        $oldStatus = $load->status instanceof \BackedEnum
            ? $load->status->value
            : (string) $load->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        if (!\App\Enums\ScheduledLoadStatus::tryFrom($newStatus)) {
            throw new \RuntimeException('Estado de carga inválido.');
        }
        if ($oldStatus === 'VALIDADO_Y_CERRADO' && ($newStatus !== 'REABIERTA' || !$user?->hasPermission('scheduled_load.reopen'))) {
            throw new \RuntimeException('El expediente cerrado requiere una reapertura autorizada.');
        }
        $load->update(['status' => $newStatus]);

        if ($load->isRetroactiveBackfillActive()) {
            $metadata['operational_date'] = $load->operationalTimestamp()->toDateString();
            $metadata['retroactive_policy_year'] = ScheduledLoad::RETROACTIVE_POLICY_YEAR;
        }

        LoadStatusHistory::query()->create([
            'scheduled_load_id' => $load->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user?->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
