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

        $load->update(['status' => $newStatus]);

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
