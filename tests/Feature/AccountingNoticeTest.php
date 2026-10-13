<?php

namespace Tests\Feature;

use App\Models\ScheduledLoad;
use App\Services\AccountingNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountingNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_notice_is_informative_and_recorded(): void
    {
        Mail::fake();

        $load = ScheduledLoad::factory()->create([
            'status' => 'VALIDADO_Y_CERRADO',
            'completion_percentage' => 100,
            'closed_at' => now(),
        ]);

        $notice = app(AccountingNoticeService::class)
            ->sendCompletionNotice($load);

        $this->assertSame('SENT', $notice->status);
        $this->assertDatabaseHas('accounting_notices', [
            'scheduled_load_id' => $load->id,
            'status' => 'SENT',
        ]);
    }
    public function test_repeated_notice_is_idempotent_and_reclosure_refreshes_payload(): void
    {
        Mail::shouldReceive('raw')->twice()->andReturnNull();
        $load = ScheduledLoad::factory()->create(['status' => 'VALIDADO_Y_CERRADO', 'closed_at' => now()]);
        $service = app(AccountingNoticeService::class);
        $first = $service->sendCompletionNotice($load);
        $key = $first->payload['closure_key'];
        $service->sendCompletionNotice($load);
        $load->update(['closed_at' => now()->addHour()]);
        $next = $service->sendCompletionNotice($load->fresh());
        $this->assertNotSame($key, $next->payload['closure_key']);
        $this->assertSame($load->closed_at->toIso8601String(), $next->payload['closed_at']);
    }

    public function test_reopened_load_does_not_send_a_completion_notice(): void
    {
        Mail::shouldReceive('raw')->never();
        $load = ScheduledLoad::factory()->create(['status' => 'REABIERTA']);
        $notice = app(AccountingNoticeService::class)->sendCompletionNotice($load);
        $this->assertSame('CANCELLED', $notice->status);
    }

}
