<?php
namespace App\Console\Commands;
use App\Models\ScheduledLoad;
use App\Services\AlertService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'siget:send-reminders';
    protected $description = 'Genera recordatorios de cargas próximas a cerrar.';

    public function handle(AlertService $alerts): int
    {
        $loads = ScheduledLoad::query()
            ->whereIn('status',['ABIERTA','EN_CAPTURA','PARCIALMENTE_ENTREGADA','REPROGRAMADA_ABIERTA'])
            ->whereBetween('effective_close_at',[now(),now()->addHours(48)])
            ->get();

        foreach ($loads as $load) {
            // Puede especializarse con un asunto de recordatorio.
            $alerts->notifyRescheduledEnabled($load);
        }

        $this->info("Recordatorios procesados: {$loads->count()}");
        return self::SUCCESS;
    }
}
