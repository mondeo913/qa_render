<?php
namespace App\Services;
use App\Jobs\SendScheduledLoadEmail;
use App\Models\ScheduledLoad;
use App\Models\User;

final class AlertService
{
    public function notifyRescheduledEnabled(ScheduledLoad $load): int
    {
        return $this->notifyScope(
            $load,
            'SIGET - Carga reprogramada habilitada',
            'La carga originalmente programada para '.$load->original_open_at->format('d/m/Y').
            ' está habilitada del '.$load->effective_open_at->format('d/m/Y H:i').
            ' al '.$load->effective_close_at->format('d/m/Y H:i').'.'
        );
    }

    public function notifyClosure(ScheduledLoad $load): int
    {
        return $this->notifyScope(
            $load,
            'SIGET - Carga validada y cerrada',
            'La carga '.$load->title.' fue validada y cerrada correctamente.'
        );
    }

    private function notifyScope(ScheduledLoad $load, string $subject, string $message): int
    {
        $users = User::query()
            ->where('status','ACTIVE')
            ->whereHas('role',fn ($role) => $role->whereIn('code',[
                'ADMINISTRADOR','DIRECTOR_GENERAL','DIRECTOR',
                'ENLACE_INSTITUCIONAL','OPERADOR'
            ]))
            ->where(function ($query) use ($load) {
                $query->where('contracting_agency_id',$load->contracting_agency_id)
                    ->orWhereHas('role',fn ($role) => $role->whereIn('code',[
                        'ADMINISTRADOR','DIRECTOR_GENERAL'
                    ]));
            })
            ->get();

        foreach ($users as $user) {
            $notification = \App\Models\NotificationRecord::query()->create([
                'user_id'=>$user->id,
                'scheduled_load_id'=>$load->id,
                'channel'=>'IN_APP',
                'status'=>'PENDING',
                'subject'=>$subject,
                'message'=>$message,
                'action_url'=>route('loads.show',$load),
            ]);

            SendScheduledLoadEmail::dispatch($notification->id);
        }

        return $users->count();
    }
}
