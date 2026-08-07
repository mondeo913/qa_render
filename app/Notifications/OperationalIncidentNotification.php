<?php
namespace App\Notifications;
use App\Models\OperationalIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OperationalIncidentNotification extends Notification implements ShouldQueue {
 use Queueable;
 public function __construct(public OperationalIncident $incident){}
 public function via(object $notifiable):array{return ['mail','database'];}
 public function toMail(object $notifiable):MailMessage{
  return (new MailMessage)->subject("SIGET {$this->incident->severity}: {$this->incident->title}")
   ->greeting('Alerta operativa SIGET')->line($this->incident->description)
   ->line("Código: {$this->incident->code}")->line("Estado: {$this->incident->status}")
   ->action('Abrir Centro de Operaciones',route('operations.incidents'));
 }
 public function toArray(object $notifiable):array{return $this->incident->only(['id','code','title','severity','status','opened_at']);}
}
