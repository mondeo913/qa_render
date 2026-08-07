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

        foreach ($items as $item) {
            $item->scheduledLoad->update([
                'status'=>ScheduledLoadStatus::REPROGRAMADA_ABIERTA,
                'traffic_light'=>TrafficLight::BLUE,
                'is_blocked'=>false,
                'block_reason'=>null,
            ]);
            $alerts->notifyRescheduledEnabled($item->scheduledLoad);
            $item->update(['status'=>'OPEN','notification_sent_at'=>now()]);
        }

        $this->info("Cargas habilitadas: {$items->count()}");
        return self::SUCCESS;
    }
}
