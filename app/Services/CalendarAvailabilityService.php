<?php
namespace App\Services;
use App\Enums\ScheduledLoadStatus;
use App\Models\CalendarSuspension;
use App\Models\ScheduledLoad;
use Carbon\CarbonInterface;

final class CalendarAvailabilityService
{
    public function isEnabled(ScheduledLoad $load, CarbonInterface $moment): bool
    {
        if ($load->is_blocked) {
            return false;
        }

        if (in_array($load->status, [
            ScheduledLoadStatus::SUSPENDIDA,
            ScheduledLoadStatus::REPROGRAMADA,
            ScheduledLoadStatus::VALIDADO_Y_CERRADO,
            ScheduledLoadStatus::CANCELADA,
            ScheduledLoadStatus::VENCIDA,
        ], true)) {
            return false;
        }

        $hasActiveSuspension = CalendarSuspension::query()
            ->where('active', true)
            ->where('block_upload', true)
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>=', $moment)
            ->where(function ($query) use ($load) {
                $query->where('applies_to_all_agencies', true)
                    ->orWhere('contracting_agency_id', $load->contracting_agency_id);
            })
            ->exists();

        if ($hasActiveSuspension) {
            return false;
        }

        return $moment->between($load->effective_open_at, $load->effective_close_at, true);
    }

    public function tooltip(ScheduledLoad $load): string
    {
        if (in_array($load->status, [
            ScheduledLoadStatus::SUSPENDIDA,
            ScheduledLoadStatus::REPROGRAMADA,
        ], true)) {
            return 'Esta fecha está reprogramada para después del 8 de septiembre de 2026.';
        }

        if ($this->isEnabled($load, now())) {
            return 'Carga habilitada dentro de la ventana efectiva.';
        }

        return 'La carga no se encuentra habilitada.';
    }
}
