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

        $status = $load->status instanceof \BackedEnum
            ? $load->status->value
            : (string) $load->status;
        $retroactive = $load->isRetroactiveBackfillActive($moment);

        if (in_array($status, [
            ScheduledLoadStatus::SUSPENDIDA->value,
            ScheduledLoadStatus::REPROGRAMADA->value,
            ScheduledLoadStatus::VALIDADO_Y_CERRADO->value,
            ScheduledLoadStatus::CANCELADA->value,
        ], true)) {
            return false;
        }

        if ($status === ScheduledLoadStatus::VENCIDA->value && !$retroactive) {
            return false;
        }

        if ($this->isSuspended($load, $moment)) {
            return false;
        }

        // This is an intake exception, not a rewrite of the source dates or action timestamps.
        if ($retroactive) {
            return true;
        }

        return $moment->between($load->effective_open_at, $load->effective_close_at, true);
    }

    public function isSuspended(ScheduledLoad $load, CarbonInterface $moment): bool
    {
        return CalendarSuspension::query()
            ->where('active', true)
            ->where('block_upload', true)
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>=', $moment)
            ->where(function ($query) use ($load) {
                $query->where('applies_to_all_agencies', true)
                    ->orWhere('contracting_agency_id', $load->contracting_agency_id);
            })
            ->exists();
    }

    public function tooltip(ScheduledLoad $load): string
    {
        if (in_array($load->status, [
            ScheduledLoadStatus::SUSPENDIDA,
            ScheduledLoadStatus::REPROGRAMADA,
        ], true)) {
            return 'Carga suspendida o reprogramada; consulte su nueva ventana autorizada.';
        }

        if ($load->isRetroactiveBackfillActive()) {
            return 'Registro retroactivo de 2026 habilitado para regularización. La pauta conserva sus fechas originales y la trazabilidad usa la fecha real de cada acción.';
        }

        if ($this->isEnabled($load, now())) {
            return 'Carga habilitada dentro de la ventana efectiva.';
        }

        return 'La carga no se encuentra habilitada.';
    }
}
