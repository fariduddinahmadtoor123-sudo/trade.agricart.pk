<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);

        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'OANDA:XAUUSD',
                    'd' => array_fill(0, 84, 22),
                ]],
            ]),
        ]);
    }

    public function test_urdu_translates_the_trading_page_and_switches_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/admin/trading/signals')
            ->post('/locale/ur')
            ->assertRedirect('/admin/trading/signals')
            ->assertSessionHas('locale', 'ur');

        $this->actingAs($user)
            ->get('/admin/trading/signals')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('کرنسیاں', false)
            ->assertSee('آر ایس آئی تکنیکی اشارے', false)
            ->assertSee('خریداری کا علاقہ / اوور سولڈ', false)
            ->assertSee('آر ایس آئی 30 سے کم اوور سولڈ خریداری کا علاقہ بتاتا ہے', false)
            ->assertSee('مثبت ہسٹوگرام بُلش مومنٹم دکھاتا ہے', false);
    }

    public function test_english_can_be_restored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/admin/trading/signals')
            ->post('/locale/ur');

        $this->actingAs($user)
            ->from('/admin/trading/signals')
            ->post('/locale/en')
            ->assertSessionHas('locale', 'en');

        $this->actingAs($user)
            ->get('/admin/trading/signals')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Currencies', false)
            ->assertSee('RSI Technical Indicators', false)
            ->assertSee('RSI below 30 signals an oversold buying zone', false)
            ->assertSee('A positive MACD histogram indicates bullish momentum', false);
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $this->from('/admin/login')->post('/locale/fr')->assertNotFound();
    }

    public function test_the_old_trading_view_address_redirects_to_signals(): void
    {
        $this->get('/admin/trading/trading-view')
            ->assertRedirect('/admin/trading/signals');
    }
}
