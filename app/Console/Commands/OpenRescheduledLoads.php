<?php
namespace App\Console\Commands;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use App\Models\LoadReschedule;
use App\Services\AlertService;
use Illuminate\Console\Command;

class OpenRescheduledLoads extends Command
{
    protected $signature = 'siget:open-rescheduled-loads';
    protected $description = 'Habilita las cargas reprogramadas cuya nueva ventana ha iniciado.';

    public function handle(AlertService $alerts): int
    {
        $items = LoadReschedule::query()
            ->with('scheduledLoad')
            ->where('status','SCHEDULED')
            ->where('new_open_at','<=',now())
            ->where('new_close_at','>=',now())
            ->get();

        $opened = 0;
        foreach ($items as $item) {
            $opened += \Illuminate\Support\Facades\DB::transaction(function () use ($item, $alerts) {
                $load = \App\Models\ScheduledLoad::query()->lockForUpdate()->findOrFail($item->scheduled_load_id);
                $item = LoadReschedule::query()->lockForUpdate()->findOrFail($item->id);
                if ($item->status !== 'SCHEDULED' || $load->status->value !== 'REPROGRAMADA'
                    || !now()->between($item->new_open_at, $item->new_close_at)
                    || app(\App\Services\CalendarAvailabilityService::class)->isSuspended($load, now())) {
                    return 0;
                }
                app(\App\Services\LoadStatusService::class)->transition($load, 'REPROGRAMADA_ABIERTA', null, 'Nueva ventana de reprogramación habilitada.');
                $load->update(['traffic_light' => TrafficLight::BLUE, 'is_blocked' => false, 'block_reason' => null]);
                $item->update(['status' => 'OPEN', 'notification_sent_at' => now()]);
                \Illuminate\Support\Facades\DB::afterCommit(fn () => $alerts->notifyRescheduledEnabled($load));
                return 1;
            });
        }

        $this->info("Cargas habilitadas: {$opened}");
        return self::SUCCESS;
    }
}
