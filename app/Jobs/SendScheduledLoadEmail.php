<?php
namespace App\Jobs;
use App\Mail\ScheduledLoadMail;
use App\Models\NotificationRecord;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendScheduledLoadEmail implements ShouldQueue
{
    use Queueable;
    public function __construct(public readonly int $notificationId) {}
    public function handle(): void
    {
        $notification = NotificationRecord::query()->findOrFail($this->notificationId);
        $user = User::query()->findOrFail($notification->user_id);
        if ($user->status !== 'ACTIVE' || !$user->role?->active || ($notification->scheduled_load_id && !app(\App\Services\AccessScopeService::class)->canAccessLoad($user, \App\Models\ScheduledLoad::query()->findOrFail($notification->scheduled_load_id)))) {
            $notification->update(['status' => 'CANCELLED']);
            return;
        }
        try {
            Mail::to($user->email)->send(new ScheduledLoadMail($notification));
            $notification->update(['status'=>'SENT','sent_at'=>now()]);
        } catch (\Throwable $exception) {
            $notification->update(['status'=>'FAILED','failure_message'=>$exception->getMessage()]);
            throw $exception;
        }
    }
}
