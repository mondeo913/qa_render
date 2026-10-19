<?php

namespace Tests\Feature;

use App\Models\ContractingAgency;
use App\Models\ScheduledLoad;
use App\Services\CalendarAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetroactiveLoadStressTest extends TestCase
{
    use RefreshDatabase;

    public function test_1500_historical_load_checks_remain_consistent_across_three_agencies(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 11:00:00', config('app.timezone')));

        $agencyIds = ContractingAgency::factory()->count(3)->create()->modelKeys();
        $availability = app(CalendarAvailabilityService::class);
        $checked = 0;

        // 500 backfill records per dependency: exercise the policy repeatedly
        // without inserting fake business events or changing their source dates.
        foreach ($agencyIds as $agencyId) {
            for ($i = 0; $i < 500; $i++) {
                $date = Carbon::parse('2026-07-01', config('app.timezone'))
                    ->addDays($i % 90);
                $openAt = $date->copy()->setTime(8, 0);
                $closeAt = $date->copy()->endOfDay();

                $load = new ScheduledLoad([
                    'contracting_agency_id' => $agencyId,
                    'original_open_at' => $openAt,
                    'original_close_at' => $closeAt,
                    'effective_open_at' => $openAt,
                    'effective_close_at' => $closeAt,
                    'status' => 'PROGRAMADA',
                    'retroactive' => true,
                    'is_blocked' => false,
                    'metadata' => [
                        'source_schedule_date' => $date->toDateString(),
                        'retroactive_registration' => true,
                        'retroactive_policy_year' => 2026,
                    ],
                ]);

                $this->assertTrue($load->isRetroactiveBackfillActive(now()));
                $this->assertTrue($availability->isEnabled($load, now()));
                $this->assertSame($date->toDateString(), $load->original_open_at->toDateString());
                $this->assertSame($date->toDateString(), $load->effective_open_at->toDateString());
                $this->assertSame($date->toDateString(), $load->original_close_at->toDateString());
                $this->assertSame($closeAt->format('Y-m-d H:i:s'), $load->operationalTimestamp()->format('Y-m-d H:i:s'));
                $checked++;
            }
        }

        $this->assertSame(1500, $checked);

        // At the year boundary the exception expires even if the stored flag remains.
        $this->travelTo(Carbon::parse('2027-01-01 11:00:00', config('app.timezone')));
        $expired = new ScheduledLoad([
            'contracting_agency_id' => $agencyIds[0],
            'original_open_at' => '2026-07-01 08:00:00',
            'original_close_at' => '2026-07-01 23:59:59',
            'effective_open_at' => '2026-07-01 08:00:00',
            'effective_close_at' => '2026-07-01 23:59:59',
            'status' => 'PROGRAMADA',
            'retroactive' => true,
            'is_blocked' => false,
            'metadata' => [
                'retroactive_registration' => true,
                'retroactive_policy_year' => 2026,
            ],
        ]);

        $this->assertFalse($expired->isRetroactiveBackfillActive(now()));
        $this->assertSame(now()->format('Y-m-d H:i:s'), $expired->operationalTimestamp()->format('Y-m-d H:i:s'));
        $this->assertFalse($availability->isEnabled($expired, now()));
    }
}
