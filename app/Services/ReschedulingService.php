<?php
namespace App\Services;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use App\Jobs\SendRescheduledLoadNotification;
use App\Models\LoadReschedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReschedulingService
{
    public function assignNewWindow(
        LoadReschedule $reschedule,
        CarbonInterface $newOpen,
        CarbonInterface $newClose,
        int $userId
    ): void {
        if ($newClose->lt($newOpen)) {
            throw new RuntimeException('La nueva fecha de cierre es anterior a la apertura.');
        }

        if ($newOpen->lte('2026-09-08 23:59:59')) {
            throw new RuntimeException('La reprogramación debe abrir después del 8 de septiembre de 2026.');
        }

        DB::transaction(function () use ($reschedule,$newOpen,$newClose,$userId) {
            $reschedule->update([
                'new_open_at'=>$newOpen,
                'new_close_at'=>$newClose,
                'status'=>'SCHEDULED',
                'reprogrammed_by'=>$userId,
                'reprogrammed_at'=>now(),
            ]);

            $reschedule->scheduledLoad()->update([
                'effective_open_at'=>$newOpen,
                'effective_close_at'=>$newClose,
                'status'=>ScheduledLoadStatus::REPROGRAMADA,
                'traffic_light'=>TrafficLight::GRAY,
                'is_blocked'=>true,
                'block_reason'=>'Reprogramada; se habilitará automáticamente en la nueva ventana.',
                'retroactive'=>true,
            ]);

            SendRescheduledLoadNotification::dispatch($reschedule->id)
                ->delay($newOpen->copy()->subHours(1));
        });
    }
}
