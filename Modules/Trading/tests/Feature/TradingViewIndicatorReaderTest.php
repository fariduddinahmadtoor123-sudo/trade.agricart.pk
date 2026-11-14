<?php

namespace Modules\Trading\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\Trading\Services\TradingViewIndicatorReader;
use Tests\TestCase;

class TradingViewIndicatorReaderTest extends TestCase
{
    public function test_it_maps_rsi_zones_and_macd_histogram_direction(): void
    {
        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'OANDA:XAUUSD',
                    'd' => [
                        29.9, 1.5, 0.4,
                        30.0, 2.0, 2.0,
                        70.0, 0.2, 0.8,
                        70.1, -0.4, -0.1,
                        null, 1.0, 0.2,
                        44.0, null, 0.2,
                    ],
                ]],
            ]),
        ]);

        $frames = app(TradingViewIndicatorReader::class)->forSymbol('OANDA:XAUUSD');

        $this->assertSame('Buying Area / Oversold', $frames[0]['rsi']['label']);
        $this->assertSame('buy', $frames[0]['rsi']['tone']);
        $this->assertSame('Bullish', $frames[0]['macd']['label']);
        $this->assertSame('Neutral', $frames[1]['rsi']['label']);
        $this->assertSame('Neutral', $frames[1]['macd']['label']);
        $this->assertSame('Neutral', $frames[2]['rsi']['label']);
        $this->assertSame('Bearish', $frames[2]['macd']['label']);
        $this->assertSame('Selling Area / Overbought', $frames[3]['rsi']['label']);
        $this->assertNull($frames[4]['rsi']);
        $this->assertNull($frames[5]['macd']);
    }

    public function test_index_and_commodity_cfds_use_the_published_scanner_market(): void
    {
        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'SP:SPX',
                    'd' => array_fill(0, 18, 50),
                ]],
            ]),
        ]);

        app(TradingViewIndicatorReader::class)->forSymbol('FOREXCOM:SPXUSD');

        Http::assertSent(function ($request): bool {
            return ($request->data()['symbols']['tickers'][0] ?? null) === 'SP:SPX'
                && in_array('RSI|30', $request->data()['columns'], true)
                && in_array('RSI', $request->data()['columns'], true)
                && in_array('MACD.macd|1M', $request->data()['columns'], true);
        });
    }

    public function test_an_unknown_symbol_returns_empty_frames_without_a_request(): void
    {
        Http::fake();

        $frames = app(TradingViewIndicatorReader::class)->forSymbol('UNKNOWN:PAIR');

        $this->assertCount(6, $frames);
        $this->assertNull($frames[0]['rsi']);
        $this->assertNull($frames[0]['macd']);
        Http::assertNothingSent();
    }
}
