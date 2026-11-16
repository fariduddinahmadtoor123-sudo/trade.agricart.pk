<?php

namespace Modules\Trading\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyfxbookOutlookEmbedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
        Cache::flush();
    }

    public function test_guests_are_redirected_from_sentiment_embed(): void
    {
        $this->get('/trading/embeds/myfxbook-outlook/51')->assertRedirect();
    }

    public function test_authenticated_users_receive_padded_myfxbook_embed(): void
    {
        Http::fake([
            'widgets.mfbcdn.net/*' => Http::response(
                '<html><head></head><body><div class="outlook">XAUUSD</div></body></html>',
                200,
            ),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/trading/embeds/myfxbook-outlook/51')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('agricart-sentiment-room', false)
            ->assertSee('.boxTitle', false)
            ->assertSee('display:none', false)
            ->assertSee('padding:170px 0 0', false)
            ->assertSee('overflow:visible', false)
            ->assertSee('https://widgets.mfbcdn.net/', false)
            ->assertSee('XAUUSD', false);
    }

    public function test_unknown_symbol_ids_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/trading/embeds/myfxbook-outlook/999')
            ->assertNotFound();
    }
}
