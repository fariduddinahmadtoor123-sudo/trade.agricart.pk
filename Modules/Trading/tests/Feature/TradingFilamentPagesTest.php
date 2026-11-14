<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Trading\Filament\Pages\TradingView;
use Tests\TestCase;

class TradingFilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament only allows non-FilamentUser models in the local environment.
        config(['app.env' => 'local']);

        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'OANDA:XAUUSD',
                    'd' => [
                        22.4, 1.2, 0.4,
                        55.0, 0.2, 0.8,
                        74.2, 0.1, 0.9,
                        48.0, 2.0, 2.0,
                        31.0, -1.0, -0.2,
                        66.0, 0.5, 0.1,
                    ],
                ]],
            ]),
        ]);
    }

    public function test_guests_are_redirected_from_trading_pages(): void
    {
        $this->get('/admin/trading/trading-view')->assertRedirect();
    }

    public function test_authenticated_users_can_open_trading_submenu_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/trading/trading-view')
            ->assertOk()
            ->assertSee('TradingView', false)
            ->assertSee('Currencies', false)
            ->assertSee('Indices', false)
            ->assertSee('Commodities', false)
            ->assertSee('Crypto', false)
            ->assertSee('EURUSD', false)
            ->assertSee('USDJPY', false)
            ->assertSee('GBPUSD', false)
            ->assertSee('US500', false)
            ->assertSee('US100', false)
            ->assertSee('US30', false)
            ->assertSee('XAUUSD', false)
            ->assertSee('USOIL', false)
            ->assertSee('XAGUSD', false)
            ->assertSee('BTCUSDT', false)
            ->assertSee('ETHUSDT', false)
            ->assertSee('SOLUSDT', false)
            ->assertSee('This symbol is currently not supported or unavailable.', false)
            ->assertDontSee('id="tv-symbol"', false)
            ->assertSee('30m', false)
            ->assertSee('1H', false)
            ->assertSee('4H', false)
            ->assertSee('1D', false)
            ->assertSee('1W', false)
            ->assertSee('1M', false)
            ->assertSee('RSI Technical Indicators', false)
            ->assertSee('MACD Momentum Signals', false)
            ->assertSee('Buying Area / Oversold', false)
            ->assertSee('Selling Area / Overbought', false)
            ->assertSee('Bullish', false)
            ->assertSee('Bearish', false)
            ->assertDontSee('>1m<', false)
            ->assertDontSee('1Y', false)
            ->assertDontSee('Investing.com Market Summary & Signals', false)
            ->assertDontSee('Technical summary by', false)
            ->assertDontSee('by TradingView', false)
            ->assertSee('ssltsw.investing.com', false)
            ->assertSee('945629', false)
            ->assertSee('data-timeframe="900"', false)
            ->assertSee('data-timeframe="1800"', false)
            ->assertSee('data-timeframe="3600"', false)
            ->assertSee('data-timeframe="18000"', false)
            ->assertSee('data-timeframe="86400"', false)
            ->assertSee('data-timeframe="week"', false)
            ->assertDontSee('Investing.com does not publish this timeframe.', false);

        $this->actingAs($user)->get('/admin/trading/open-trades')->assertOk();
        $this->actingAs($user)->get('/admin/trading/mt5-terminal')->assertOk();
        $this->actingAs($user)->get('/admin/trading/python-bridge-logs')->assertOk();
    }

    public function test_selecting_a_sector_symbol_reloads_rsi_and_macd(): void
    {
        Http::fake(function ($request) {
            $ticker = $request->data()['symbols']['tickers'][0] ?? '';
            $rsi = $ticker === 'FX:EURUSD' ? 81.2 : 22.4;
            $row = array_fill(0, 18, 50);
            $row[0] = $rsi;
            $row[1] = $ticker === 'FX:EURUSD' ? 0.2 : 1.2;
            $row[2] = $ticker === 'FX:EURUSD' ? 0.8 : 0.4;

            return Http::response([
                'data' => [[
                    's' => $ticker,
                    'd' => $row,
                ]],
            ]);
        });

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TradingView::class)
            ->assertSee('Buying Area / Oversold')
            ->set('symbol', 'FX:EURUSD')
            ->assertSee('Selling Area / Overbought')
            ->assertSee('Bearish');

        Http::assertSent(function ($request): bool {
            return ($request->data()['symbols']['tickers'][0] ?? null) === 'FX:EURUSD';
        });
    }
}
