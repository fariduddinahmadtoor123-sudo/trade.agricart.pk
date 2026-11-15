<?php

namespace Modules\Trading\Tests\Feature;

use Carbon\CarbonImmutable;
use Modules\Trading\Services\HighImpactUsdEventSchedule;
use Tests\TestCase;

class HighImpactUsdEventScheduleTest extends TestCase
{
    public function test_it_returns_the_next_high_impact_usd_event_after_now(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 10:00:00', 'America/New_York');
        $schedule = app(HighImpactUsdEventSchedule::class);

        $next = $schedule->next($now);

        $this->assertNotNull($next);
        $this->assertSame('retail', $next['key']);
        $this->assertSame('USD', $next['currency']);
        $this->assertSame(3, $next['impact']);
        $this->assertSame('Retail Sales', $next['name']);
        $this->assertTrue($next['at']->greaterThan($now));
        $this->assertSame('2026-11-17', $next['at']->toDateString());
    }

    public function test_it_highlights_todays_primary_usd_release(): void
    {
        $now = CarbonImmutable::parse('2026-11-17 07:00:00', 'America/New_York');
        $schedule = app(HighImpactUsdEventSchedule::class);

        $today = $schedule->todaysHighlight($now);

        $this->assertNotNull($today);
        $this->assertSame('retail', $today['key']);
        $this->assertTrue($today['is_today']);
    }

    public function test_it_generates_first_friday_nfp_dates(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 10:00:00', 'America/New_York');
        $schedule = app(HighImpactUsdEventSchedule::class);

        $nfp = $schedule->upcoming(12, $now)->firstWhere('key', 'nfp');

        $this->assertNotNull($nfp);
        $this->assertSame(CarbonImmutable::FRIDAY, $nfp['at']->dayOfWeek);
        $this->assertLessThanOrEqual(7, $nfp['at']->day);
        $this->assertSame(8, $nfp['at']->hour);
        $this->assertSame(30, $nfp['at']->minute);
    }

    public function test_it_respects_currency_and_impact_filters(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 10:00:00', 'America/New_York');
        $schedule = app(HighImpactUsdEventSchedule::class);

        $this->assertNull($schedule->next($now, [
            'currencies' => ['EUR'],
            'impacts' => [3],
            'range' => 'week',
        ]));

        $this->assertNull($schedule->next($now, [
            'currencies' => ['USD'],
            'impacts' => [1],
            'range' => 'week',
        ]));

        $next = $schedule->next($now, [
            'currencies' => ['USD'],
            'impacts' => [3],
            'range' => 'week',
        ]);

        $this->assertNotNull($next);
        $this->assertSame('retail', $next['key']);
    }

    public function test_it_scopes_upcoming_list_to_the_active_day_or_week_window(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 10:00:00', 'America/New_York');
        $schedule = app(HighImpactUsdEventSchedule::class);

        $week = $schedule->upcoming(5, $now, [
            'currencies' => [],
            'impacts' => [3],
            'range' => 'week',
        ]);

        $this->assertNotEmpty($week);
        $this->assertTrue($week->every(
            fn (array $event): bool => $event['at']->lessThanOrEqualTo($now->addDays(7)->endOfDay()),
        ));

        $fomc = $schedule->upcoming(8, $now, [
            'currencies' => ['USD'],
            'impacts' => [3],
            'range' => 'week',
        ], ignoreRangeWindow: true)->firstWhere('key', 'fomc');

        $this->assertNotNull($fomc);
        $this->assertSame('2026-12-09', $fomc['at']->toDateString());
        $this->assertSame(14, $fomc['at']->hour);
    }
}
