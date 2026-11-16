<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Pages\Mt5Terminal;
use Modules\Trading\Filament\Pages\OpenTrades;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;
use Tests\TestCase;

class Mt5BridgeTest extends TestCase
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

    public function test_guest_sync_is_unauthorized(): void
    {
        $this->postJson('/api/v1/trading/mt5/sync', [])->assertUnauthorized();
    }

    public function test_sync_updates_the_account_quotes_open_position_and_history(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $account = PropAccount::factory()->create([
            'firm_name' => 'FundedHive',
            'account_number' => '88001',
            'initial_balance' => 100000,
        ]);

        $this->postJson('/api/v1/trading/mt5/sync', [
            'account' => [
                'firm_name' => 'FundedHive',
                'account_number' => '88001',
                'balance' => 100250.5,
                'equity' => 100260,
                'day_start_balance' => 100000,
                'today_pnl' => 260,
                'floating_pnl' => 12.5,
            ],
            'ticks' => [
                ['symbol' => 'EURUSD', 'bid' => 1.10100, 'ask' => 1.10120],
            ],
            'positions' => [
                [
                    'ticket' => 777,
                    'symbol' => 'EURUSD',
                    'side' => 'buy',
                    'lots' => 0.1,
                    'price_open' => 1.10020,
                    'sl' => 1.09800,
                    'tp' => 1.10500,
                    'profit' => 12.5,
                ],
            ],
        ])->assertOk();

        $account->refresh();
        $this->assertSame('100250.50', $account->current_balance);
        $this->assertSame('100260.00', $account->equity);
        $this->assertSame('260.00', $account->today_pnl);
        $this->assertDatabaseHas('trading_logs', [
            'symbol' => 'EURUSD',
            'source' => 'mt5-bridge',
        ]);

        $order = TerminalOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(777, $order->mt5_ticket);
        $this->assertSame('12.50', $order->floating_pnl);
        $this->assertSame(TerminalOrderStatus::Open, $order->status);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(OpenTrades::class)
            ->assertSee('777', false)
            ->assertSee('EURUSD', false)
            ->assertSee('12.50', false);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->assertSee('#777', false)
            ->assertSee('+12.50', false);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/trading/mt5/sync', [
            'account' => [
                'account_number' => '88001',
                'balance' => 100270,
                'equity' => 100270,
            ],
            'deals' => [
                [
                    'position_ticket' => 777,
                    'symbol' => 'EURUSD',
                    'side' => 'sell',
                    'lots' => 0.1,
                    'price' => 1.10220,
                    'profit' => 20,
                    'entry' => 'out',
                    'time' => '2026-11-16T12:00:00Z',
                ],
            ],
        ])->assertOk();

        $order->refresh();
        $this->assertSame(TerminalOrderStatus::Closed, $order->status);
        $this->assertSame('20.00', $order->realized_pnl);
        $this->assertEquals(1.10220, (float) $order->exit_price);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->assertSee('+20.00', false)
            ->assertSee('No open positions', false);
    }

    public function test_a_disconnected_terminal_queues_the_order_for_the_bridge(): void
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
        Http::fake(function (): void {
            throw new ConnectionException('offline');
        });

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->set('orderSymbol', 'EURUSD')
            ->set('lots', '0.10')
            ->set('stopLoss', '1.09800')
            ->call('buy')
            ->assertSee('The MT5 terminal is not connected. The order is queued', false);

        $order = TerminalOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(TerminalOrderStatus::Pending, $order->status);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/trading/mt5/orders/pending?account_number='.$account->account_number)
            ->assertOk()
            ->assertJsonPath('data.0.client_order_id', $order->id)
            ->assertJsonPath('data.0.symbol', 'EURUSD')
            ->assertJsonPath('data.0.side', TerminalOrderSide::Buy->value);

        $this->postJson('/api/v1/trading/mt5/orders/'.$order->id.'/execution', [
            'status' => 'filled',
            'ticket' => 4321,
            'price' => 1.10025,
        ])->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.mt5_ticket', 4321);

        $this->assertSame(TerminalOrderStatus::Open, $order->fresh()->status);
        $this->assertEquals(1.10025, (float) $order->fresh()->entry_price);
    }

    public function test_a_broker_rejection_does_not_open_a_position(): void
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
            'http://127.0.0.1:8765/orders' => Http::response(['ok' => false, 'message' => 'Not enough money'], 422),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('accountId', (string) $account->id)
            ->call('login')
            ->set('orderSymbol', 'EURUSD')
            ->set('lots', '0.10')
            ->set('stopLoss', '1.09800')
            ->call('buy')
            ->assertSee('Not enough money', false);

        $this->assertSame(TerminalOrderStatus::Rejected, TerminalOrder::query()->first()?->status);
    }

    public function test_repeated_bridge_quotes_keep_one_row_per_symbol(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = [
            'account' => [
                'account_number' => '88001',
                'balance' => 20,
                'equity' => 20,
            ],
            'ticks' => [
                ['symbol' => 'eurusd', 'bid' => 1.10100, 'ask' => 1.10120],
            ],
        ];

        $this->postJson('/api/v1/trading/mt5/sync', $payload)->assertOk();
        $payload['ticks'][0]['bid'] = 1.10200;
        $this->postJson('/api/v1/trading/mt5/sync', $payload)->assertOk();

        $this->assertSame(1, TradingLog::query()->where('source', 'mt5-bridge')->count());
        $this->assertEquals(1.102, (float) TradingLog::query()->value('bid'));
    }
}
