<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Trading\Filament\Pages\SmartAlertsPage;
use Modules\Trading\Services\SmartAlertFeed;
use Modules\Trading\Services\SmartAlertPreferences;
use Tests\TestCase;

class SmartAlertsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
        Cache::flush();
    }

    public function test_authenticated_users_can_open_smart_alerts_desk(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/trading/alerts')
            ->assertOk()
            ->assertSee('Smart Alerts', false)
            ->assertSee('Alert Rules', false)
            ->assertSee('High-Impact News Alerts', false)
            ->assertSee('30-Minute Pre-Event Warning', false)
            ->assertSee('15-Minute Pre-Event Warning', false)
            ->assertSee('Currency Filters', false)
            ->assertSee('Triggered and Upcoming Alerts', false)
            ->assertSee('ff-alerts__split', false)
            ->assertDontSee('Price and signal alerts will appear in this space.', false);
    }

    public function test_toggling_rules_and_currencies_persists_preferences(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SmartAlertsPage::class)
            ->assertSet('highImpactNews', true)
            ->assertSet('warn15', true)
            ->assertSet('warn30', true)
            ->assertSet('currencies', ['USD'])
            ->call('toggleWarn15')
            ->assertSet('warn15', false)
            ->call('toggleCurrency', 'EUR')
            ->assertSet('currencies', ['USD', 'EUR'])
            ->call('toggleCurrency', 'USD')
            ->assertSet('currencies', ['EUR']);

        $preferences = app(SmartAlertPreferences::class)->forUser($user->id);

        $this->assertFalse($preferences['warn_15']);
        $this->assertSame(['EUR'], $preferences['currencies']);
    }

    public function test_activity_feed_respects_disabled_high_impact_rule(): void
    {
        $now = CarbonImmutable::parse('2026-11-15 10:00:00', 'America/New_York');
        $feed = app(SmartAlertFeed::class);

        $active = $feed->activity([
            'high_impact_news' => true,
            'warn_15' => true,
            'warn_30' => true,
            'currencies' => ['USD'],
        ], $now);

        $this->assertNotEmpty($active);
        $this->assertTrue(collect($active)->every(
            fn (array $row): bool => $row['currency'] === 'USD' && $row['impact'] === 3,
        ));

        $disabled = $feed->activity([
            'high_impact_news' => false,
            'warn_15' => true,
            'warn_30' => true,
            'currencies' => ['USD'],
        ], $now);

        $this->assertSame([], $disabled);
    }

    public function test_activity_feed_builds_pre_event_warning_rows(): void
    {
        $now = CarbonImmutable::parse('2026-11-17 08:00:00', 'America/New_York');
        $feed = app(SmartAlertFeed::class);

        $rows = collect($feed->activity([
            'high_impact_news' => true,
            'warn_15' => true,
            'warn_30' => true,
            'currencies' => ['USD'],
        ], $now));

        $this->assertTrue($rows->contains(
            fn (array $row): bool => $row['event_key'] === 'retail'
                && $row['kind'] === 'warn_30'
                && $row['status'] === 'triggered',
        ));

        $this->assertTrue($rows->contains(
            fn (array $row): bool => $row['event_key'] === 'retail'
                && $row['kind'] === 'warn_15'
                && $row['status'] === 'upcoming',
        ));
    }
}
