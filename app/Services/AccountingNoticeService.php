<?php

namespace App\Services;

use App\Models\AccountingNotice;
use App\Models\ScheduledLoad;
use Illuminate\Support\Facades\Mail;

final class AccountingNoticeService
{
    public function sendCompletionNotice(ScheduledLoad $load): AccountingNotice
    {
        $recipients = config('siget.accounting_recipients', [
            'contabilidad@siget.local',
        ]);

        $notice = AccountingNotice::query()->firstOrCreate(
            ['scheduled_load_id' => $load->id],
            [
                'recipients' => $recipients,
                'status' => 'PENDING',
                'payload' => [
                    'scheduled_load_id' => $load->id,
                    'title' => $load->title,
                    'closed_at' => $load->closed_at?->toIso8601String(),
                    'message' => 'La carga concluyó al 100 %. Aviso informativo; no constituye facturación.',
                ],
            ]
        );

        try {
            Mail::raw(
                "La carga {$load->title} fue validada y cerrada al 100 %. "
                ."Este aviso es exclusivamente informativo para Contabilidad.",
                function ($message) use ($recipients, $load) {
                    $message
                        ->to($recipients)
                        ->subject("SIGET · Conclusión de carga #{$load->id}");
                }
            );

            $notice->update([
                'status' => 'SENT',
                'sent_at' => now(),
            ]);

            $load->update(['accounting_notified_at' => now()]);
        } catch (\Throwable $exception) {
            $notice->update([
                'status' => 'FAILED',
                'failure_message' => $exception->getMessage(),
            ]);
        }

        return $notice->fresh();
    }
}
