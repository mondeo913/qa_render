<?php
namespace App\Jobs;
use App\Models\LoadReschedule;
use App\Services\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRescheduledLoadNotification implements ShouldQueue
{
    use Queueable;
    public function __construct(public readonly int $rescheduleId) {}
    public function handle(AlertService $alerts): void
    {
        $reschedule = LoadReschedule::query()->with('scheduledLoad')->findOrFail($this->rescheduleId);
        $load = $reschedule->scheduledLoad;
        if ($load->effective_open_at->isFuture()) {
            $this->release($load->effective_open_at->diffInSeconds(now()));
            return;
        }
        $load->update([
            'status'=>'REPROGRAMADA_ABIERTA',
            'traffic_light'=>'BLUE',
            'is_blocked'=>false,
            'block_reason'=>null,
        ]);
        $alerts->notifyRescheduledEnabled($load);
        $reschedule->update(['status'=>'OPEN','notification_sent_at'=>now()]);
    }
}
