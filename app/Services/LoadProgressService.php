<?php

namespace App\Services;

use App\Enums\DeliverableStatus;
use App\Models\ScheduledLoad;

final class LoadProgressService
{
    public function recalculate(ScheduledLoad $load): float
    {
        $deliverables = $load->deliverables()->get();

        if ($deliverables->isEmpty()) {
            $percentage = 0.0;
        } else {
            $weights = [
                DeliverableStatus::PENDIENTE->value => 0,
                DeliverableStatus::EN_CAPTURA->value => 20,
                DeliverableStatus::ENVIADO->value => 45,
                DeliverableStatus::EN_REVISION->value => 60,
                DeliverableStatus::OBSERVADO->value => 45,
                DeliverableStatus::CORREGIDO->value => 65,
                DeliverableStatus::VALIDADO->value => 90,
                DeliverableStatus::CERRADO->value => 100,
            ];

            $percentage = round(
                $deliverables->avg(fn ($item) =>
                    $weights[$item->status instanceof DeliverableStatus
                        ? $item->status->value
                        : (string) $item->status] ?? 0
                ),
                2
            );
        }

        $load->forceFill(['completion_percentage' => $percentage])->save();

        return $percentage;
    }
}
