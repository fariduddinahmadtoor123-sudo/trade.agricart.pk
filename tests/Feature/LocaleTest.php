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
                    'd' => array_fill(0, 18, 22),
                ]],
            ]),
        ]);
    }

    public function test_urdu_translates_the_trading_page_and_switches_direction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/admin/trading/trading-view')
            ->post('/locale/ur')
            ->assertRedirect('/admin/trading/trading-view')
            ->assertSessionHas('locale', 'ur');

        $this->actingAs($user)
            ->get('/admin/trading/trading-view')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('کرنسیاں', false)
            ->assertSee('آر ایس آئی تکنیکی اشارے', false)
            ->assertSee('خریداری کا علاقہ / اوور سولڈ', false);
    }

    public function test_english_can_be_restored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/admin/trading/trading-view')
            ->post('/locale/ur');

        $this->actingAs($user)
            ->from('/admin/trading/trading-view')
            ->post('/locale/en')
            ->assertSessionHas('locale', 'en');

        $this->actingAs($user)
            ->get('/admin/trading/trading-view')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Currencies', false)
            ->assertSee('RSI Technical Indicators', false);
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $this->from('/admin/login')->post('/locale/fr')->assertNotFound();
    }
}
