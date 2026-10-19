<?php

namespace Tests\Feature;

use App\Models\CalendarSuspension;
use App\Models\ScheduledLoad;
use App\Services\CalendarAvailabilityService;
use App\Services\SuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetroactiveLoadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_2026_load_is_enabled_for_backfill_only_during_2026(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 11:00:00', config('app.timezone')));

        $load = ScheduledLoad::factory()->create([
            'original_open_at' => '2026-07-07 08:00:00',
            'original_close_at' => '2026-07-07 23:59:59',
            'effective_open_at' => '2026-07-07 08:00:00',
            'effective_close_at' => '2026-07-07 23:59:59',
            'retroactive' => true,
            'status' => 'PROGRAMADA',
            'is_blocked' => false,
            'metadata' => ['retroactive_registration' => true, 'retroactive_policy_year' => 2026],
        ]);

        $this->assertTrue($load->isRetroactiveBackfillActive(now()));
        $this->assertTrue(app(CalendarAvailabilityService::class)->isEnabled($load, now()));

        $this->travelTo(Carbon::parse('2027-01-01 11:00:00', config('app.timezone')));
        $this->assertFalse($load->fresh()->isRetroactiveBackfillActive(now()));
        $this->assertFalse(app(CalendarAvailabilityService::class)->isEnabled($load->fresh(), now()));
    }

    public function test_historical_suspension_does_not_block_a_retroactive_2026_backfill(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 11:00:00', config('app.timezone')));

        CalendarSuspension::query()->create([
            'name' => 'Suspensión histórica para prueba retroactiva',
            'starts_at' => '2026-08-25 00:00:00',
            'ends_at' => '2026-09-08 23:59:59',
            'applies_to_all_agencies' => true,
            'block_upload' => true,
            'exclude_from_compliance' => true,
            'active' => true,
        ]);

        $load = ScheduledLoad::factory()->create([
            'original_open_at' => '2026-08-28 08:00:00',
            'original_close_at' => '2026-08-28 23:59:59',
            'effective_open_at' => '2026-08-28 08:00:00',
            'effective_close_at' => '2026-08-28 23:59:59',
            'retroactive' => true,
            'status' => 'PROGRAMADA',
            'is_blocked' => false,
            'metadata' => ['retroactive_registration' => true, 'retroactive_policy_year' => 2026],
        ]);

        $this->assertFalse(app(SuspensionService::class)->applyToLoad($load));
        $this->assertFalse($load->fresh()->is_blocked);
        $this->assertSame('PROGRAMADA', $load->fresh()->status->value);
        $this->assertTrue(app(CalendarAvailabilityService::class)->isEnabled($load->fresh(), now()));
    }

    public function test_retroactive_mode_does_not_bypass_an_explicitly_blocked_load(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 11:00:00', config('app.timezone')));

        $load = ScheduledLoad::factory()->create([
            'original_open_at' => '2026-07-07 08:00:00',
            'original_close_at' => '2026-07-07 23:59:59',
            'effective_open_at' => '2026-07-07 08:00:00',
            'effective_close_at' => '2026-07-07 23:59:59',
            'retroactive' => true,
            'status' => 'PROGRAMADA',
            'is_blocked' => true,
            'metadata' => ['retroactive_registration' => true, 'retroactive_policy_year' => 2026],
        ]);

        $this->assertTrue($load->fresh()->isRetroactiveBackfillActive(now()));
        $this->assertFalse(app(CalendarAvailabilityService::class)->isEnabled($load, now()));
    }
}
