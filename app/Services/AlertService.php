<?php

namespace App\Services;

use App\Jobs\SendScheduledLoadEmail;
use App\Models\Evidence;
use App\Models\NotificationRecord;
use App\Models\ScheduledLoad;
use App\Models\User;

final class AlertService
{
    public function __construct(private readonly AccessScopeService $access) {}

    public function notifyRescheduledEnabled(ScheduledLoad $load): int
    {
        return $this->notifyScope($load, 'SIGET - Carga reprogramada habilitada',
            'La carga está habilitada del '.$load->effective_open_at->format('d/m/Y H:i').' al '.$load->effective_close_at->format('d/m/Y H:i').'.');
    }

    public function notifyClosure(ScheduledLoad $load): int
    {
        return $this->notifyScope($load, 'SIGET - Carga validada y cerrada', 'La carga '.$load->title.' fue validada y cerrada correctamente.');
    }

    public function notifyCorrection(ScheduledLoad $load, string $reason): int
    {
        return $this->notifyScope($load, 'SIGET - Corrección autorizada', $reason);
    }

    public function notifyEvidence(Evidence $evidence, string $subject): int
    {
        return $this->notifyScope($evidence->scheduledLoad, 'SIGET - '.$subject,
            $evidence->title.' · Versión '.$evidence->current_version, $evidence);
    }

    private function notifyScope(ScheduledLoad $load, string $subject, string $message, ?Evidence $evidence = null): int
    {
        $count = 0;
        foreach (User::query()->with('role')->where('status', 'ACTIVE')->get() as $user) {
            if (!$user->role?->active || !$this->access->canAccessLoad($user, $load)
                || ($evidence && !$this->access->canAccessEvidence($user, $evidence))) {
                continue;
            }
            $notification = NotificationRecord::query()->create(['user_id' => $user->id,
                'scheduled_load_id' => $load->id, 'channel' => 'IN_APP', 'status' => 'PENDING',
                'subject' => $subject, 'message' => $message, 'action_url' => route('loads.show', $load)]);
            SendScheduledLoadEmail::dispatch($notification->id)->afterCommit();
            $count++;
        }
        return $count;
    }
}
