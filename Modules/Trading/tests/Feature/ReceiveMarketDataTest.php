<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Trading\Models\TradingLog;
use Tests\TestCase;

class ReceiveMarketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/trading/market-data', [
            'symbol' => 'XAUUSD',
            'bid' => 2650.12,
            'ask' => 2650.45,
        ])->assertUnauthorized();
    }

    public function test_it_stores_a_single_market_tick(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/trading/market-data', [
            'symbol' => 'XAUUSD',
            'bid' => 2650.12,
            'ask' => 2650.45,
            'last' => 2650.30,
            'volume' => 1.25,
            'source' => 'python-bridge',
            'payload' => ['tick_id' => 'abc-123'],
            'feed_at' => '2026-11-14T10:00:00Z',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.0.symbol', 'XAUUSD')
            ->assertJsonPath('data.0.source', 'python-bridge');

        $this->assertDatabaseHas('trading_logs', [
            'symbol' => 'XAUUSD',
            'source' => 'python-bridge',
        ]);
    }

    public function test_it_stores_a_batch_of_market_ticks(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/trading/market-data', [
            'ticks' => [
                [
                    'symbol' => 'EURUSD',
                    'bid' => 1.0850,
                    'ask' => 1.0852,
                ],
                [
                    'symbol' => 'GBPUSD',
                    'bid' => 1.2650,
                    'ask' => 1.2653,
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data');

        $this->assertSame(2, TradingLog::query()->count());
    }

    public function test_it_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/trading/market-data', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['symbol']);
    }

    public function test_authenticated_users_can_list_recent_market_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        TradingLog::factory()->count(3)->create();

        $this->getJson('/api/v1/trading/market-data')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
