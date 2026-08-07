<?php
namespace App\Services;
use App\Enums\ScheduledLoadStatus;
use App\Enums\TrafficLight;
use App\Models\CalendarSuspension;
use App\Models\LoadReschedule;
use App\Models\ScheduledLoad;

final class SuspensionService
{
    public function applyToLoad(ScheduledLoad $load): bool
    {
        $suspension = CalendarSuspension::query()
            ->where('active', true)
            ->where(function ($query) use ($load) {
                $query->where('applies_to_all_agencies', true)
                    ->orWhere('contracting_agency_id', $load->contracting_agency_id);
            })
            ->where('starts_at', '<=', $load->original_close_at)
            ->where('ends_at', '>=', $load->original_open_at)
            ->first();

        if (!$suspension) {
            return false;
        }

        $load->update([
            'status' => ScheduledLoadStatus::REPROGRAMADA,
            'traffic_light' => TrafficLight::GRAY,
            'is_blocked' => true,
            'block_reason' => 'Esta fecha está reprogramada para después del 8 de septiembre de 2026.',
            'retroactive' => true,
        ]);

        LoadReschedule::query()->firstOrCreate(
            ['scheduled_load_id'=>$load->id,'suspension_id'=>$suspension->id],
            [
                'old_open_at'=>$load->original_open_at,
                'old_close_at'=>$load->original_close_at,
                'reason'=>'Suspensión institucional global',
                'retroactive'=>true,
                'status'=>'PENDING',
            ]
        );

        return true;
    }
}
