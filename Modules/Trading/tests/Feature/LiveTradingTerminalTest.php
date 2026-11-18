<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Pages\Mt5Terminal;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;
use Modules\Trading\Services\TerminalOrderDispatcher;
use Tests\TestCase;

class LiveTradingTerminalTest extends TestCase
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

    public function test_market_watch_and_account_bar_follow_the_bridge_snapshot(): void
    {
        $user = User::factory()->create();
        $account = PropAccount::factory()->withRule()->create([
            'current_balance' => 100000,
            'equity' => 100250,
            'today_pnl' => 150,
            'snapshot_at' => now(),
        ]);

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 9.99999,
            'ask' => 9.99999,
            'feed_at' => now()->subMinute(),
        ]);
        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
            'feed_at' => now(),
        ]);
        TradingLog::factory()->create([
            'symbol' => 'XAUUSD',
            'bid' => 2650.10,
            'ask' => 2650.40,
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->assertSee('Market Watch', false)
            ->assertSee('term-desk__table--fit', false)
            ->assertDontSee('data-stamp', false)
            ->assertDontSee('age_ms', false)
            ->assertSee('Open an account session before sending an order.', false)
            ->assertSee('1.10000', false)
            ->assertSee('1.10020', false)
            ->assertSee('2650.10', false)
            ->assertSee('2650.40', false)
            ->assertDontSee('9.99999', false)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->assertSee('100,000.00', false)
            ->assertSee('100,250.00', false)
            ->assertSee('+150.00', false);
    }

    public function test_buy_opens_a_position_and_close_records_live_pnl(): void
    {
        $user = User::factory()->create();
        $account = PropAccount::factory()->withRule()->create([
            'status' => PropAccountStatus::Challenge,
            'current_balance' => 100000,
            'equity' => 100000,
            'day_start_balance' => 100000,
            'today_pnl' => 0,
            'snapshot_at' => now(),
        ]);

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/orders' => Http::response(['ok' => true, 'ticket' => 555, 'price' => 1.10020], 200),
            'http://127.0.0.1:8765/orders/close' => Http::response(['ok' => true, 'price' => 1.10120, 'profit' => 10], 200),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->set('orderSymbol', 'EURUSD')
            ->set('lots', '0.10')
            ->set('stopLoss', '1.09800')
            ->set('takeProfit', '1.10500')
            ->call('buy')
            ->assertSee('is open at 1.10020', false);

        $order = TerminalOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(TerminalOrderStatus::Open, $order->status);
        $this->assertSame(555, $order->mt5_ticket);
        $this->assertEquals(1.10020, (float) $order->entry_price);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://127.0.0.1:8765/orders'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['symbol'] === 'EURUSD'
                && $request['side'] === 'buy'
                && (float) $request['lots'] === 0.10;
        });

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10120,
            'ask' => 1.10140,
            'feed_at' => now()->addMinute(),
        ]);

        $component
            ->call('selectSymbol', 'EURUSD')
            ->assertSee('+10.00', false)
            ->call('closePosition', $order->id)
            ->assertSee('PnL +10.00', false)
            ->assertSee('No open positions', false);

        $this->assertSame(TerminalOrderStatus::Closed, $order->fresh()->status);
        $this->assertEquals(10.0, (float) $order->fresh()->realized_pnl);
    }

    public function test_funded_buy_without_a_stop_is_rejected(): void
    {
        $user = User::factory()->create();
        $account = PropAccount::factory()->withRule()->create([
            'current_balance' => 100000,
            'equity' => 100000,
            'snapshot_at' => now(),
        ]);

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
        ]);

        Http::preventStrayRequests();

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->set('orderSymbol', 'EURUSD')
            ->set('lots', '0.10')
            ->call('buy')
            ->assertSee('This account requires a stop-loss before the trade can pass.', false);

        $this->assertSame(0, TerminalOrder::query()->count());
    }

    public function test_close_leaves_another_sessions_position_open(): void
    {
        $user = User::factory()->create();
        $first = PropAccount::factory()->withRule()->create(['status' => PropAccountStatus::Challenge]);
        $second = PropAccount::factory()->withRule()->create(['status' => PropAccountStatus::Challenge]);
        $order = TerminalOrder::factory()->create([
            'prop_account_id' => $first->id,
            'status' => TerminalOrderStatus::Open,
        ]);

        Http::preventStrayRequests();

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $second->id)
            ->call('login')
            ->call('closePosition', $order->id)
            ->assertSee('That position is not on this session.', false);

        $this->assertSame(TerminalOrderStatus::Open, $order->fresh()->status);
    }

    public function test_sell_fills_at_the_bid_and_gold_uses_the_ounce_contract(): void
    {
        $account = PropAccount::factory()->withRule()->create([
            'status' => PropAccountStatus::Challenge,
            'current_balance' => 100000,
            'equity' => 100000,
            'day_start_balance' => 100000,
            'today_pnl' => 0,
            'snapshot_at' => now(),
        ]);

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
        ]);
        TradingLog::factory()->create([
            'symbol' => 'XAUUSD',
            'bid' => 2650.10,
            'ask' => 2650.40,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/orders/close')) {
                return Http::response(['ok' => true, 'price' => 2651.40, 'profit' => 10]);
            }

            $price = $request['symbol'] === 'XAUUSD' ? 2650.40 : 1.10000;
            $ticket = $request['symbol'] === 'XAUUSD' ? 900 : 800;

            return Http::response(['ok' => true, 'ticket' => $ticket, 'price' => $price]);
        });

        $dispatcher = app(TerminalOrderDispatcher::class);

        $sell = $dispatcher->place($account, 'EURUSD', TerminalOrderSide::Sell, '0.01', '1.10200', '1.09000');
        $this->assertTrue($sell->accepted);
        $this->assertEquals(1.10000, (float) $sell->order?->entry_price);
        Http::assertSent(fn (Request $request): bool => $request['side'] === 'sell' && $request['symbol'] === 'EURUSD');

        $gold = $dispatcher->place($account, 'XAUUSD', TerminalOrderSide::Buy, '0.10', '2640', '2700');
        $this->assertTrue($gold->accepted);

        TradingLog::factory()->create([
            'symbol' => 'XAUUSD',
            'bid' => 2651.40,
            'ask' => 2651.70,
            'feed_at' => now()->addMinute(),
        ]);

        $closed = $dispatcher->close($account, (int) $gold->order?->id);

        $this->assertTrue($closed->accepted);
        $this->assertEquals(10.0, (float) $closed->order?->realized_pnl);
    }

    public function test_a_stop_on_the_wrong_side_is_rejected(): void
    {
        $account = PropAccount::factory()->withRule()->create([
            'status' => PropAccountStatus::Challenge,
            'current_balance' => 100000,
            'snapshot_at' => now(),
        ]);

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
        ]);

        Http::preventStrayRequests();

        $result = app(TerminalOrderDispatcher::class)->place(
            $account,
            'EURUSD',
            TerminalOrderSide::Buy,
            '0.10',
            '1.20000',
            '',
        );

        $this->assertFalse($result->accepted);
        $this->assertSame(['The stop loss is on the wrong side of the price.'], $result->messages);
        $this->assertSame(0, TerminalOrder::query()->count());
    }
}
