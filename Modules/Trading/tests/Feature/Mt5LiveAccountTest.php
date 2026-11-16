<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Pages\Mt5Terminal;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;
use Tests\TestCase;

class Mt5LiveAccountTest extends TestCase
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

    public function test_the_terminal_form_connects_a_live_account_and_buys_without_prop_rules(): void
    {
        $user = User::factory()->create();

        TradingLog::factory()->create([
            'symbol' => 'EURUSD',
            'bid' => 1.10000,
            'ask' => 1.10020,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response([
                'ok' => true,
                'login' => 252912077,
                'balance' => 20.13,
                'equity' => 20.13,
                'server' => 'MetaQuotes-Demo',
                'name' => 'Farid',
                'company' => 'MetaQuotes',
            ], 200),
            'http://127.0.0.1:8765/quotes' => Http::response([
                'ok' => true,
                'quotes' => [
                    ['symbol' => 'EURUSD', 'bid' => 1.10000, 'ask' => 1.10020, 'time_msc' => 0, 'age_ms' => 12],
                ],
            ], 200),
            'http://127.0.0.1:8765/orders' => Http::response([
                'ok' => true,
                'ticket' => 900,
                'price' => 1.10020,
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->assertSee('Account number', false)
            ->assertDontSee('Add a prop account', false)
            ->set('mt5Login', '252912077')
            ->call('connectMt5')
            ->assertSee('Connected to 252912077', false)
            ->assertSee('20.13', false)
            ->set('orderSymbol', 'EURUSD')
            ->set('lots', '0.01')
            ->call('buy')
            ->assertSee('is open at 1.10020', false);

        $order = TerminalOrder::query()->first();

        $this->assertNotNull($order);
        $this->assertNull($order->prop_account_id);
        $this->assertSame(TerminalOrderStatus::Open, $order->status);
        $this->assertSame(900, $order->mt5_ticket);
        $this->assertSame(0, PropAccount::query()->count());
        $this->assertSame('252912077', Mt5Account::query()->value('login'));
    }

    public function test_a_grid_click_sends_one_percent_protection_without_a_typed_stop(): void
    {
        $user = User::factory()->create();

        TradingLog::factory()->create([
            'symbol' => 'XAUUSDM',
            'bid' => 3866.50,
            'ask' => 3866.80,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response([
                'ok' => true,
                'login' => 252912077,
                'balance' => 20.99,
                'equity' => 20.99,
                'server' => 'Exness-MT5Real33',
            ], 200),
            'http://127.0.0.1:8765/quotes' => Http::response([
                'ok' => true,
                'quotes' => [
                    ['symbol' => 'XAUUSDm', 'bid' => 3866.50, 'ask' => 3866.80, 'time_msc' => 1, 'age_ms' => 8],
                ],
            ], 200),
            'http://127.0.0.1:8765/orders' => Http::response([
                'ok' => true,
                'ticket' => 901,
                'price' => 3866.80,
                'stop_loss' => 3866.59,
                'take_profit' => 3867.01,
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->assertSee('High', false)
            ->assertSee('Double click to trade', false)
            ->set('mt5Login', '252912077')
            ->call('connectMt5')
            ->call('placeQuote', 'XAUUSDM', 'buy', '0.01')
            ->assertSee('is open at 3866.80', false);

        $order = TerminalOrder::query()->first();

        $this->assertNotNull($order);
        $this->assertNull($order->prop_account_id);
        $this->assertEquals(3866.59, (float) $order->stop_loss);
        $this->assertEquals(3867.01, (float) $order->take_profit);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://127.0.0.1:8765/orders'
                && $request['symbol'] === 'XAUUSDM'
                && $request['side'] === 'buy'
                && (float) $request['lots'] === 0.01
                && (float) $request['risk_percent'] === 1.0
                && $request['stop_loss'] === null;
        });
    }

    public function test_password_and_server_are_sent_to_the_bridge_session(): void
    {
        $user = User::factory()->create();

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/session' => Http::response([
                'ok' => true,
                'login' => 252912077,
                'balance' => 20.13,
                'equity' => 20.13,
                'server' => 'MetaQuotes-Demo',
                'name' => 'Farid',
                'company' => 'MetaQuotes',
            ], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('mt5Login', '252912077')
            ->set('mt5Password', 'secret-pass')
            ->set('mt5Server', 'MetaQuotes-Demo')
            ->call('connectMt5')
            ->assertSet('mt5Password', '')
            ->assertSee('Connected to 252912077', false);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://127.0.0.1:8765/session'
                && $request['login'] === '252912077'
                && $request['password'] === 'secret-pass'
                && $request['server'] === 'MetaQuotes-Demo';
        });

        $account = Mt5Account::query()->first();

        $this->assertNotNull($account);
        $this->assertSame('MetaQuotes-Demo', $account->server);
        $this->assertNotSame('secret-pass', $account->getRawOriginal('password'));
    }

    public function test_an_order_is_refused_when_the_bridge_token_is_missing(): void
    {
        $user = User::factory()->create();

        TradingLog::factory()->create([
            'symbol' => 'XAUUSDM',
            'bid' => 3866.50,
            'ask' => 3866.80,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8765/health' => Http::response([
                'ok' => true,
                'login' => 252912077,
                'balance' => 20.99,
                'equity' => 20.99,
                'server' => 'Exness-MT5Real33',
            ], 200),
            'http://127.0.0.1:8765/quotes' => Http::response([
                'ok' => true,
                'quotes' => [
                    ['symbol' => 'XAUUSDm', 'bid' => 3866.50, 'ask' => 3866.80, 'time_msc' => 1, 'age_ms' => 8],
                ],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('mt5Login', '252912077')
            ->call('connectMt5');

        config(['trading.mt5.bridge_token' => null]);

        $component
            ->call('placeQuote', 'XAUUSDM', 'buy', '0.01')
            ->assertSee('The MT5 bridge is not configured.', false);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/orders'));
        $this->assertSame(0, TerminalOrder::query()->count());
    }

    public function test_a_queued_live_order_keeps_one_percent_protection(): void
    {
        $user = User::factory()->create();

        TradingLog::factory()->create([
            'symbol' => 'XAUUSDM',
            'bid' => 3866.50,
            'ask' => 3866.80,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/orders')) {
                throw new ConnectionException('offline');
            }

            if (str_contains($request->url(), '/quotes')) {
                return Http::response([
                    'ok' => true,
                    'quotes' => [
                        ['symbol' => 'XAUUSDm', 'bid' => 3866.50, 'ask' => 3866.80],
                    ],
                ], 200);
            }

            return Http::response([
                'ok' => true,
                'login' => 252912077,
                'balance' => 20.99,
                'equity' => 20.99,
                'server' => 'Exness-MT5Real33',
            ], 200);
        });

        Livewire::actingAs($user)
            ->test(Mt5Terminal::class)
            ->set('mt5Login', '252912077')
            ->call('connectMt5')
            ->call('placeQuote', 'XAUUSDM', 'buy', '0.01')
            ->assertSee('The MT5 terminal is not connected. The order is queued', false);

        $order = TerminalOrder::query()->first();

        $this->assertNotNull($order);
        $this->assertNull($order->prop_account_id);
        $this->assertNull($order->stop_loss);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/trading/mt5/orders/pending?account_number=252912077')
            ->assertOk()
            ->assertJsonPath('data.0.client_order_id', $order->id)
            ->assertJsonPath('data.0.risk_percent', 1);
    }
}
