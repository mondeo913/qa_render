<?php
namespace Tests\Feature;
use App\Models\CalendarSuspension;
use App\Models\ScheduledLoad;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspensionRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_august_25_to_september_8_is_reprogrammed(): void
    {
        CalendarSuspension::query()->create([
            'name'=>'Suspensión global',
            'starts_at'=>'2026-08-25 00:00:00',
            'ends_at'=>'2026-09-08 23:59:59',
            'applies_to_all_agencies'=>true,
            'block_upload'=>true,
            'exclude_from_compliance'=>true,
            'active'=>true,
        ]);

        $load = ScheduledLoad::factory()->create([
            'original_open_at'=>'2026-08-28 08:00:00',
            'original_close_at'=>'2026-08-28 18:00:00',
            'effective_open_at'=>'2026-08-28 08:00:00',
            'effective_close_at'=>'2026-08-28 18:00:00',
            'status'=>'PROGRAMADA',
        ]);

        $this->assertTrue(app(SuspensionService::class)->applyToLoad($load));
        $this->assertSame('REPROGRAMADA',$load->fresh()->status->value);
        $this->assertTrue($load->fresh()->is_blocked);
    }
}
