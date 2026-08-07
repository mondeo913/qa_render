<?php
namespace App\Mail;
use App\Models\NotificationRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledLoadMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public readonly NotificationRecord $notification) {}
    public function build(): self
    {
        return $this->subject($this->notification->subject)
            ->view('emails.scheduled-load')
            ->with(['notification'=>$this->notification]);
    }
}