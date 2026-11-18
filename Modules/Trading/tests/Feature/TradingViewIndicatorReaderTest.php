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
                        ...$this->frame(29.9, 1.5, 0.4, 103, 102, 98, 100.6, 99.4, 100, 102, 100, 101),
                        ...$this->frame(30.0, 2.0, 2.0, 100, 100, 100, 100.3, 99.8, 100, 102, 100, 101),
                        ...$this->frame(70.0, 0.2, 0.8, 100, 99, 101, 103, 97, 100, 102, 100, 101),
                        ...$this->frame(70.1, -0.4, -0.1, 100, 97, 103, 110, 90, 100, 102, 100, 101),
                        ...$this->frame(null, 1.0, 0.2, null, null, null, null, null, null, null, null, null),
                        ...$this->frame(44.0, null, 0.2, 100, 101, 99, 102, 98, 100, 90, 100, 95),
                    ],
                ]],
            ]),
        ]);

        $frames = app(TradingViewIndicatorReader::class)->forSymbol('OANDA:XAUUSD');

        $this->assertSame('Buying Area / Oversold', $frames[0]['rsi']['label']);
        $this->assertSame('buy', $frames[0]['rsi']['tone']);
        $this->assertSame('Bullish', $frames[0]['macd']['label']);
        $this->assertSame('Strong Bullish', $frames[0]['ma']['label']);
        $this->assertSame('strong_buy', $frames[0]['ma']['key']);
        $this->assertSame('Normal Volatility', $frames[0]['bollinger']['label']);
        $this->assertSame([
            'pp' => '101.00',
            'r1' => '102.00',
            'r2' => '103.00',
            's1' => '100.00',
            's2' => '99.000',
        ], $frames[0]['pivot']);

        $snapshot = app(TradingViewIndicatorReader::class)->snapshot('OANDA:XAUUSD');
        $this->assertSame('100.00', $snapshot['price']);
        $this->assertSame($frames, $snapshot['frames']);
        $this->assertSame('Neutral', $frames[1]['rsi']['label']);
        $this->assertSame('Neutral', $frames[1]['macd']['label']);
        $this->assertSame('Neutral', $frames[1]['ma']['label']);
        $this->assertSame('Neutral', $frames[2]['rsi']['label']);
        $this->assertSame('Bearish', $frames[2]['macd']['label']);
        $this->assertSame('Bearish Trend', $frames[2]['ma']['label']);
        $this->assertSame('Selling Area / Overbought', $frames[3]['rsi']['label']);
        $this->assertSame('Expanded / High Volatility', $frames[3]['bollinger']['label']);
        $this->assertNull($frames[4]['rsi']);
        $this->assertNull($frames[4]['ma']);
        $this->assertNull($frames[4]['pivot']);
        $this->assertNull($frames[5]['macd']);
        $this->assertNull($frames[5]['pivot']);
    }

    public function test_index_and_commodity_cfds_use_the_published_scanner_market(): void
    {
        Http::fake([
            'scanner.tradingview.com/*' => Http::response([
                'data' => [[
                    's' => 'SP:SPX',
                    'd' => array_fill(0, 72, 50),
                ]],
            ]),
        ]);

        app(TradingViewIndicatorReader::class)->forSymbol('FOREXCOM:SPXUSD');

        Http::assertSent(function ($request): bool {
            $columns = $request->data()['columns'] ?? [];

            return ($request->data()['symbols']['tickers'][0] ?? null) === 'SP:SPX'
                && in_array('RSI|30', $columns, true)
                && in_array('RSI', $columns, true)
                && in_array('MACD.macd|1M', $columns, true)
                && in_array('EMA50|30', $columns, true)
                && in_array('BB.upper|60', $columns, true)
                && in_array('high[1]|30', $columns, true)
                && in_array('low[1]|60', $columns, true)
                && in_array('close[1]', $columns, true)
                && in_array('close[1]|1W', $columns, true)
                && in_array('high[1]|1M', $columns, true)
                && ! in_array('Pivot.M.Classic.Middle', $columns, true)
                && ! in_array('Pivot.M.Classic.Middle|30', $columns, true);
        });
    }

    public function test_an_unknown_symbol_returns_empty_frames_without_a_request(): void
    {
        Http::fake();

        $frames = app(TradingViewIndicatorReader::class)->forSymbol('UNKNOWN:PAIR');

        $this->assertCount(6, $frames);
        $this->assertNull($frames[0]['rsi']);
        $this->assertNull($frames[0]['macd']);
        $this->assertNull($frames[0]['ma']);
        $this->assertNull($frames[0]['bollinger']);
        $this->assertNull($frames[0]['pivot']);
        $this->assertNull(app(TradingViewIndicatorReader::class)->snapshot('UNKNOWN:PAIR')['price']);
        Http::assertNothingSent();
    }

    /**
     * @return list<float|null>
     */
    private function frame(
        ?float $rsi,
        ?float $macd,
        ?float $signal,
        ?float $close,
        ?float $ema50,
        ?float $ema200,
        ?float $upper,
        ?float $lower,
        ?float $basis,
        ?float $previousHigh,
        ?float $previousLow,
        ?float $previousClose,
    ): array {
        return [$rsi, $macd, $signal, $close, $ema50, $ema200, $upper, $lower, $basis, $previousHigh, $previousLow, $previousClose];
    }
}
