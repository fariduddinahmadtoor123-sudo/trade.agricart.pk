<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Trading\Filament\Pages\EconomicCalendarPage;
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
        Cache::flush();

        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'OANDA:XAUUSD',
                    'd' => [
                        22.4, 1.2, 0.4, 1920, 1910, 1890, 1920, 1880, 1900, 1900, 1910, 1920, 1890, 1880,
                        55.0, 0.2, 0.8, 1900, 1895, 1905, 1910, 1890, 1900, 1900, 1910, 1920, 1890, 1880,
                        74.2, 0.1, 0.9, 1900, 1915, 1885, 1930, 1870, 1900, 1900, 1910, 1920, 1890, 1880,
                        48.0, 2.0, 2.0, 1900, 1900, 1900, 1902, 1898, 1900, 1900, 1910, 1920, 1890, 1880,
                        31.0, -1.0, -0.2, 1900, 1880, 1920, 1940, 1860, 1900, 1900, 1910, 1920, 1890, 1880,
                        66.0, 0.5, 0.1, 1900, 1920, 1880, 1950, 1850, 1900, 1900, 1910, 1920, 1890, 1880,
                    ],
                ]],
            ]),
        ]);
    }

    public function test_guests_are_redirected_from_trading_pages(): void
    {
        $this->get('/admin/trading/signals')->assertRedirect();
    }

    public function test_authenticated_users_can_open_trading_submenu_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/trading/signals')
            ->assertOk()
            ->assertSee('Signals', false)
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
            ->assertSee('Moving Averages', false)
            ->assertSee('Bollinger Bands', false)
            ->assertSee('Pivot Points', false)
            ->assertSee('Buying Area / Oversold', false)
            ->assertSee('Selling Area / Overbought', false)
            ->assertSee('Bullish', false)
            ->assertSee('Bearish', false)
            ->assertSee('Strong Bullish', false)
            ->assertSee('EMA 50', false)
            ->assertSee('Squeeze / Low Volatility', false)
            ->assertSee('>PP<', false)
            ->assertSee('>R1<', false)
            ->assertSee('>S2<', false)
            ->assertDontSee('>1m<', false)
            ->assertDontSee('1Y', false)
            ->assertDontSee('Investing.com Market Summary & Signals', false)
            ->assertDontSee('Technical summary by', false)
            ->assertDontSee('by TradingView', false)
            ->assertSee('ssltsw.investing.com', false)
            ->assertSee('945629', false)
            ->assertSee('Market Sentiment', false)
            ->assertSee('tv-sentiment', false)
            ->assertSee('myfxbook-outlook', false)
            ->assertSee('Data not available', false)
            ->assertSee('FOREXCOM:USOIL', false)
            ->assertSee('8849', false)
            ->assertDontSee('Community Outlook by Myfxbook', false)
            ->assertDontSee('Shows Myfxbook community long/short positioning for the selected symbol when that pair is available.', false)
            ->assertDontSee('Sentiment data for this symbol is not available from Myfxbook.', false)
            ->assertDontSee('Investing.com does not publish a technical summary for this symbol.', false)
            ->assertSee('data-timeframe="900"', false)
            ->assertSee('data-timeframe="1800"', false)
            ->assertSee('data-timeframe="3600"', false)
            ->assertSee('data-timeframe="18000"', false)
            ->assertSee('data-timeframe="86400"', false)
            ->assertSee('data-timeframe="week"', false)
            ->assertDontSee('Investing.com does not publish this timeframe.', false);

        $this->actingAs($user)->get('/admin/trading/open-trades')->assertOk();
        $this->actingAs($user)->get('/admin/trading/mt5-terminal')->assertOk();

        $this->actingAs($user)
            ->get('/admin/trading/sessions')
            ->assertOk()
            ->assertSee('Market Sessions', false)
            ->assertSee('Global Market Sessions', false)
            ->assertSee('ff-sessions', false)
            ->assertSee('ff-sessions__gauge', false)
            ->assertSee('ff-sessions__lane', false)
            ->assertSee('ff-sessions__segment', false)
            ->assertSee('ff-sessions__now', false)
            ->assertDontSee('ff-sessions__overlap-divider', false)
            ->assertDontSee('Sydney–Tokyo', false)
            ->assertSee('London', false)
            ->assertSee('New York', false)
            ->assertSee('Sydney', false)
            ->assertSee('Tokyo', false)
            ->assertSee('13:00–22:00 PKT', false);

        $this->actingAs($user)
            ->get('/admin/trading/calendar')
            ->assertOk()
            ->assertSeeInOrder([
                'Signals',
                'Economic Calendar / News',
                'Market Sessions',
                'Open Trades',
            ], false)
            ->assertDontSee('Forex Factory', false)
            ->assertDontSee('high-impact fundamental news', false)
            ->assertSee('features=datepicker,filters', false)
            ->assertDontSee('features=timezone', false)
            ->assertSee('ff-calendar__split', false)
            ->assertSee('ff-calendar__rail', false)
            ->assertSee('ff-calendar__highlights', false)
            ->assertSee('ff-calendar__countdown', false)
            ->assertSee('Next High-Impact USD Data', false)
            ->assertSee('Speeches stay in the calendar', false)
            ->assertSee('Upcoming Highlights', false)
            ->assertSee('ff-calendar__side', false)
            ->assertSee('ff-calendar__legend', false)
            ->assertSee('ff-calendar__clock', false)
            ->assertSee('sslecal2.investing.com', false)
            ->assertSee('ff-calendar__investing', false)
            ->assertSee('Local timezone', false)
            ->assertSee('Legend', false)
            ->assertSee('Event Types', false)
            ->assertSee('Volatility / Impact', false)
            ->assertSee('Speech', false)
            ->assertSee('High Volatility Expected', false)
            ->assertSee('ff-calendar__usd-ref', false)
            ->assertSee('High-Impact USD Data Reference', false)
            ->assertSee('FOMC Rate Decision', false)
            ->assertSee('Non-Farm Payrolls (NFP)', false)
            ->assertSee('Retail Sales', false)
            ->assertSee('ff-calendar__select', false)
            ->assertSee('Today', false)
            ->assertSee('This week', false)
            ->assertSee('page-info-dock', false)
            ->assertDontSee('Economic releases and market headlines will appear in this space.', false);
        $this->actingAs($user)
            ->get('/admin/trading/alerts')
            ->assertOk()
            ->assertSee('Smart Alerts', false)
            ->assertSee('Alert Rules', false)
            ->assertSee('Triggered and Upcoming Alerts', false)
            ->assertDontSee('Price and signal alerts will appear in this space.', false);
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

    public function test_economic_calendar_investing_filters_update_the_official_widget_url(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EconomicCalendarPage::class)
            ->assertSee('sslecal2.investing.com', false)
            ->assertSee('calType=day', false)
            ->assertSee('timeZone=14', false)
            ->assertSee('countries=25,32,6,37,72', false)
            ->assertSee('importance=1,2,3', false)
            ->assertSee('features=datepicker,filters', false)
            ->assertDontSee('features=timezone', false)
            ->assertSee('Legend')
            ->assertSee('Speech')
            ->assertSee('Local timezone')
            ->call('syncInvestingTimeZoneFromBrowser', 5)
            ->assertSee('timeZone=22', false)
            ->assertSee('ff-calendar__select', false)
            ->call('setInvestingRange', 'week')
            ->assertSee('calType=week', false)
            ->call('setInvestingImpactFilter', 'high')
            ->assertSee('importance=3', false)
            ->assertDontSee('importance=1,2,3', false)
            ->call('setInvestingImpactFilter', 'low')
            ->assertSee('importance=1', false)
            ->assertDontSee('importance=3', false)
            ->call('setInvestingImpactFilter', 'all')
            ->assertSee('importance=1,2,3', false)
            ->call('setInvestingCurrencyFilter', 'JPY')
            ->assertSee('countries=35', false)
            ->assertDontSee('countries=5,72,4,35', false)
            ->call('setInvestingCurrencyFilter', 'all')
            ->assertSee('countries=25,32,6,37,72', false);
    }
}
