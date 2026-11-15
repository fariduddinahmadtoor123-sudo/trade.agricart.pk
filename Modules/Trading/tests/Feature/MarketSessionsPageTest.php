<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Trading\Filament\Pages\MarketSessionsPage;
use Modules\Trading\Services\MarketSessionSchedule;
use Tests\TestCase;

class MarketSessionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_market_sessions(): void
    {
        $this->get('/admin/trading/sessions')->assertRedirect();
    }

    public function test_authenticated_users_can_open_market_sessions_desk(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:30:00', 'UTC'));

        $this->actingAs($user)
            ->get('/admin/trading/sessions')
            ->assertOk()
            ->assertSee('Market Sessions', false)
            ->assertSee('Global Market Sessions', false)
            ->assertSee('ff-sessions__lane', false)
            ->assertSee('ff-sessions__segment', false)
            ->assertSee('ff-sessions__plot', false)
            ->assertSee('ff-sessions__now', false)
            ->assertSee('ff-sessions__weekend', false)
            ->assertSee('PKT', false)
            ->assertSee('03:00–12:00 PKT', false)
            ->assertSee('18:00–03:00 PKT', false)
            ->assertSee('Saturday–Sunday: the forex market is normally closed', false)
            ->assertSee('is-weekend', false)
            ->assertDontSee('Sydney–Tokyo', false)
            ->assertSee('ff-sessions__cards', false)
            ->assertSee('ffMarketSessionsClock', false)
            ->assertSee('left:', false)
            ->assertSee('dir="ltr"', false);
    }

    public function test_clock_snapshot_uses_pkt_and_forex_weekend_window(): void
    {
        $schedule = app(MarketSessionSchedule::class);

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:30:00', 'UTC'));

        $closed = $schedule->clockSnapshot();

        $this->assertFalse($closed['forex_week_open']);
        $this->assertSame('15:30:00', $closed['local_clock']);
        $this->assertSame('15:30', $closed['local_clock_short']);
        $this->assertSame(15, $closed['current_pkt_hour']);
        $this->assertSame([], $closed['active_keys']);
        $this->assertSame(__('trading.market_sessions.status_closed'), $closed['overlap_status']);
        $this->assertEqualsWithDelta((15 * 60 + 30) / 1440 * 100, $closed['now_percent'], 0.01);

        Carbon::setTestNow(Carbon::parse('2026-11-16 14:30:00', 'UTC'));

        $open = $schedule->clockSnapshot();

        $this->assertTrue($open['forex_week_open']);
        $this->assertSame('19:30:00', $open['local_clock']);
        $this->assertSame(['london', 'new_york'], $open['active_keys']);
        $this->assertStringContainsString('overlap', strtolower($open['overlap_status']));
    }

    public function test_forex_week_boundaries_match_friday_close_and_sunday_open(): void
    {
        $schedule = app(MarketSessionSchedule::class);

        $this->assertTrue($schedule->isForexWeekOpen(Carbon::parse('2026-11-13 21:59:00', 'UTC')));
        $this->assertFalse($schedule->isForexWeekOpen(Carbon::parse('2026-11-13 22:00:00', 'UTC')));
        $this->assertFalse($schedule->isForexWeekOpen(Carbon::parse('2026-11-14 12:00:00', 'UTC')));
        $this->assertFalse($schedule->isForexWeekOpen(Carbon::parse('2026-11-15 21:59:00', 'UTC')));
        $this->assertTrue($schedule->isForexWeekOpen(Carbon::parse('2026-11-15 22:00:00', 'UTC')));
    }

    public function test_timeline_sessions_are_positioned_in_pkt(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(MarketSessionsPage::class);

        $rows = $component->instance()->timelineSessions();

        $this->assertCount(4, $rows);
        $this->assertSame('sydney', $rows[0]['key']);
        $this->assertSame(3 * 60, $rows[0]['open_pkt']);
        $this->assertSame(12 * 60, $rows[0]['close_pkt']);
        $this->assertSame(18 * 60, $rows[3]['open_pkt']);
        $this->assertSame(3 * 60, $rows[3]['close_pkt']);
        $this->assertNotEmpty($rows[0]['segments']);
        $this->assertCount(2, $rows[3]['segments']);
    }

    public function test_schedule_desk_cards_use_risk_color_ladder(): void
    {
        $cards = app(MarketSessionSchedule::class)->deskCards();

        $this->assertCount(4, $cards);
        $this->assertSame('sydney', $cards[0]['key']);
        $this->assertSame('low', $cards[0]['activity']);
        $this->assertSame('#15803d', $cards[0]['color']);
        $this->assertSame('03:00–12:00 PKT', $cards[0]['hours_label']);
        $this->assertSame('london', $cards[2]['key']);
        $this->assertSame('high', $cards[2]['activity']);
        $this->assertSame('#991b1b', $cards[3]['color']);
        $this->assertSame('18:00–03:00 PKT', $cards[3]['hours_label']);
    }

    public function test_sessions_page_info_guide_explains_pkt_clock_line_overlap(): void
    {
        $guide = strtolower(__('trading.guides.sessions.user'));

        $this->assertStringContainsString('clock line', $guide);
        $this->assertStringContainsString('pkt', $guide);
        $this->assertStringContainsString('2 open', $guide);
        $this->assertStringNotContainsString('3-session overlap', $guide);
        $this->assertStringNotContainsString('utc timeline', $guide);
    }
}
