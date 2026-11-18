<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Pages\Mt5Terminal;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Services\Mt5AccountSession;
use Tests\TestCase;

class TerminalBridgeDeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'local',
            'trading.mt5.bridge_url' => 'http://127.0.0.1:8765',
            'trading.mt5.bridge_token' => 'test-token',
        ]);
    }

    public function test_the_watchlist_stops_at_ten_symbols(): void
    {
        $user = User::factory()->create();
        $symbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'XAUUSD', 'BTCUSD', 'USOIL', 'XAGUSD', 'EURJPY', 'GBPJPY', 'AUDUSD'];

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/watchlist' => Http::response(['ok' => true], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Mt5Terminal::class);

        foreach ($symbols as $symbol) {
            $component->call('addWatch', $symbol);
        }

        $component
            ->call('addWatch', 'NZDUSD')
            ->assertSee('The watchlist already has 10 symbols. Remove one first.', false)
            ->assertSet('watchlist', $symbols);

        Http::assertSentCount(10);
    }

    public function test_a_category_loads_broker_symbol_details(): void
    {
        $user = User::factory()->create();

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/board?category=Metals' => Http::response([
                'ok' => true,
                'quotes' => [[
                    'symbol' => 'XAUUSDm',
                    'bid' => 3755.79,
                    'ask' => 3756.03,
                    'spread' => 0.24,
                    'volume_min' => 0.01,
                    'volume_max' => 100,
                    'volume_step' => 0.01,
                    'swap_long' => -42.1,
                    'swap_short' => 18.4,
                    'contract_size' => 100,
                ]],
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->call('showCategory', 'Metals')
            ->assertSet('boardRows.0.symbol', 'XAUUSDm')
            ->assertSet('boardRows.0.spread', 0.24)
            ->assertSet('boardRows.0.contract_size', 100);
    }

    public function test_candles_and_older_history_come_from_the_bridge(): void
    {
        $user = User::factory()->create();

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/rates?symbol=EURUSD&timeframe=1h&count=80' => Http::response([
                'ok' => true,
                'candles' => [[
                    'time' => '2026-11-18T12:00:00',
                    'open' => 1.1,
                    'high' => 1.2,
                    'low' => 1.0,
                    'close' => 1.15,
                ]],
            ], 200),
            'http://127.0.0.1:8765/history?days=90' => Http::response([
                'ok' => true,
                'deals' => [[
                    'position_ticket' => 44,
                    'symbol' => 'GBPUSDm',
                    'lots' => 0.2,
                    'price' => 1.31,
                    'profit' => 12.5,
                    'time' => '2026-10-01T09:00:00',
                ]],
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('orderSymbol', 'EURUSD')
            ->call('loadCandles', '1h')
            ->assertSee('1.15', false)
            ->set('historyDays', '90')
            ->call('loadHistory')
            ->assertSee('GBPUSDm', false)
            ->assertSee('2026-10-01T09:00:00', false);
    }

    public function test_selecting_a_grid_symbol_loads_its_candles(): void
    {
        $user = User::factory()->create();

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/rates?symbol=EURUSD&timeframe=15m&count=80' => Http::response([
                'ok' => true,
                'candles' => [[
                    'time' => '2026-11-18T12:00:00',
                    'open' => 1.1,
                    'high' => 1.2,
                    'low' => 1.0,
                    'close' => 1.15,
                ]],
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->call('selectChart', 'EURUSD')
            ->assertSet('orderSymbol', 'EURUSD')
            ->assertSet('candleFrame', '15m')
            ->assertSee('EURUSD', false)
            ->assertSee('1.15', false);
    }

    public function test_partial_close_keeps_the_remaining_lots_open(): void
    {
        $user = User::factory()->create();
        $account = Mt5Account::factory()->create();
        $order = TerminalOrder::factory()->create([
            'prop_account_id' => null,
            'mt5_account_id' => $account->id,
            'lots' => 0.10,
            'mt5_ticket' => 77,
            'status' => TerminalOrderStatus::Open,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/orders/close' => Http::response([
                'ok' => true,
                'price' => 1.101,
                'profit' => 4,
                'lots_left' => 0.05,
            ], 200),
        ]);

        session([Mt5AccountSession::KEY => $account->id]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->call('partialClose', $order->id, '0.05')
            ->assertSee('0.05 lots remain', false);

        $fresh = $order->fresh();
        $this->assertSame(TerminalOrderStatus::Open, $fresh->status);
        $this->assertEquals(0.05, (float) $fresh->lots);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://127.0.0.1:8765/orders/close'
                && (float) $request['lots'] === 0.05;
        });
    }
}
