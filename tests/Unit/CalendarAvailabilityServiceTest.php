<?php
namespace Tests\Unit;
use App\Models\ScheduledLoad;
use App\Services\CalendarAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprogrammed_load_is_disabled(): void
    {
        $load = ScheduledLoad::factory()->create([
            'status'=>'REPROGRAMADA',
            'is_blocked'=>true,
            'effective_open_at'=>'2026-09-10 08:00:00',
            'effective_close_at'=>'2026-09-12 18:00:00',
        ]);

        $this->assertFalse(
            app(CalendarAvailabilityService::class)
                ->isEnabled($load,Carbon::parse('2026-09-11 10:00'))
        );
    }
}