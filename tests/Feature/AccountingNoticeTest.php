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
}
