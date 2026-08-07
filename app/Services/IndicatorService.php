<?php
namespace App\Services;
use App\Models\ContractingAgency;
use App\Models\ScheduledLoad;
use Illuminate\Support\Collection;

final class IndicatorService
{
    public function agencySummary(?int $agencyId = null): Collection
    {
        return ContractingAgency::query()
            ->when($agencyId,fn ($query) => $query->whereKey($agencyId))
            ->withCount([
                'scheduledLoads as measurable_loads' => fn ($query) =>
                    $query->whereNotIn('status',['SUSPENDIDA','REPROGRAMADA','CANCELADA']),
                'scheduledLoads as closed_loads' => fn ($query) =>
                    $query->where('status','VALIDADO_Y_CERRADO'),
                'scheduledLoads as reprogrammed_loads' => fn ($query) =>
                    $query->whereIn('status',['SUSPENDIDA','REPROGRAMADA','REPROGRAMADA_ABIERTA']),
                'scheduledLoads as expired_loads' => fn ($query) =>
                    $query->where('status','VENCIDA'),
            ])
            ->get()
            ->map(function ($agency) {
                $agency->compliance_percentage = $agency->measurable_loads === 0
                    ? 0
                    : round(100 * $agency->closed_loads / $agency->measurable_loads,2);
                return $agency;
            });
    }
}
