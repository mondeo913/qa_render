<?php
namespace App\Services;

use App\Models\{CalendarSuspension, LoadReschedule, ScheduledLoad, User};
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use App\Exceptions\WorkflowException;

final class ReschedulingService
{
    public function reschedule(ScheduledLoad $load, User $user, CarbonInterface $opens, CarbonInterface $closes, string $reason): void
    {
        DB::transaction(function () use ($load, $user, $opens, $closes, $reason) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($load->id);
            $record = $load->reschedules()->whereIn('status', ['PENDING', 'SCHEDULED'])->latest('id')->first();
            $record ??= $load->reschedules()->create(['old_open_at' => $load->effective_open_at,
                'old_close_at' => $load->effective_close_at, 'reason' => $reason, 'status' => 'PENDING']);
            $record->update(['reason' => $reason]);
            $this->assignNewWindow($record, $opens, $closes, $user->id);
        });
    }

    public function assignNewWindow(LoadReschedule $reschedule, CarbonInterface $newOpen, CarbonInterface $newClose, int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $load = $reschedule->scheduledLoad;
        if (!$user->hasPermission('calendar.reschedule') || !app(AccessScopeService::class)->canManageAgency($user, $load->contracting_agency_id)) {
            throw new WorkflowException('No puede reprogramar esta dependencia.');
        }
        if ($newClose->lte($newOpen) || $newClose->lte(now())) {
            throw new WorkflowException('Indique una ventana futura válida.');
        }
        if (CalendarSuspension::query()->where('active', true)->where('block_upload', true)
            ->where('starts_at', '<=', $newClose)->where('ends_at', '>=', $newOpen)
            ->where(fn ($q) => $q->where('applies_to_all_agencies', true)->orWhere('contracting_agency_id', $load->contracting_agency_id))->exists()) {
            throw new WorkflowException('La nueva ventana se cruza con una suspensión vigente.');
        }
        DB::transaction(function () use ($reschedule, $newOpen, $newClose, $user) {
            $load = ScheduledLoad::query()->lockForUpdate()->findOrFail($reschedule->scheduled_load_id);
            if (!in_array($load->status->value, ['PROGRAMADA', 'ABIERTA', 'VENCIDA', 'SUSPENDIDA', 'REPROGRAMADA'], true)) {
                throw new WorkflowException('El expediente en revisión o cerrado requiere corrección o reapertura, no reprogramación.');
            }
            $reschedule->update(['new_open_at' => $newOpen, 'new_close_at' => $newClose, 'status' => 'SCHEDULED', 'reprogrammed_by' => $user->id, 'reprogrammed_at' => now()]);
            app(LoadStatusService::class)->transition($load, 'REPROGRAMADA', $user, $reschedule->reason);
            $load->update(['effective_open_at' => $newOpen, 'effective_close_at' => $newClose, 'traffic_light' => 'GRAY',
                'is_blocked' => true, 'block_reason' => 'Reprogramada; se habilitará automáticamente en la nueva ventana.', 'retroactive' => true]);
            $load->deliverables()->whereIn('status', ['PENDIENTE', 'EN_CAPTURA'])->update(['due_at' => $newClose]);
            app(AuditService::class)->record('scheduled_load.rescheduled', $load, [], ['authorized_by' => $user->id, 'reschedule_id' => $reschedule->id]);
        });
    }
}
