<?php

namespace Modules\Trading\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class TradingViewIndicatorReader
{
    private const ENDPOINT = 'https://scanner.tradingview.com/global/scan';

    private const COLUMNS_PER_INTERVAL = 12;

    /**
     * Scanner tickers for the page symbols. Cash and spot markets are used
     * where the CFD ticker has no scanner row.
     *
     * @var array<string, string>
     */
    private const SCANNER_SYMBOLS = [
        'FX:EURUSD' => 'FX:EURUSD',
        'FX:USDJPY' => 'FX:USDJPY',
        'FX:GBPUSD' => 'FX:GBPUSD',
        'FOREXCOM:SPXUSD' => 'SP:SPX',
        'FOREXCOM:NSXUSD' => 'NASDAQ:NDX',
        'FOREXCOM:US30' => 'DJ:DJI',
        'OANDA:XAUUSD' => 'OANDA:XAUUSD',
        'FOREXCOM:USOIL' => 'FX:USOIL',
        'OANDA:XAGUSD' => 'TVC:SILVER',
        'BINANCE:BTCUSDT' => 'BINANCE:BTCUSDT',
        'BINANCE:ETHUSDT' => 'BINANCE:ETHUSDT',
        'BINANCE:SOLUSDT' => 'BINANCE:SOLUSDT',
    ];

    /**
     * @var array<string, string>
     */
    private const INTERVALS = [
        '30m' => '30',
        '1H' => '60',
        '4H' => '240',
        '1D' => '',
        '1W' => '1W',
        '1M' => '1M',
    ];

    /**
     * Bandwidth percent below this is a squeeze for that timeframe.
     *
     * @var array<string, float>
     */
    private const BB_SQUEEZE = [
        '30m' => 0.5,
        '1H' => 0.8,
        '4H' => 1.5,
        '1D' => 3.0,
        '1W' => 5.0,
        '1M' => 8.0,
    ];

    /**
     * Bandwidth percent above this is an expansion for that timeframe.
     *
     * @var array<string, float>
     */
    private const BB_EXPAND = [
        '30m' => 1.5,
        '1H' => 2.5,
        '4H' => 4.0,
        '1D' => 8.0,
        '1W' => 14.0,
        '1M' => 20.0,
    ];

    /**
     * @return list<array{
     *     label: string,
     *     rsi: array{value: float, display: string, width: float, tone: string, label: string}|null,
     *     macd: array{histogram: float, display: string, strength: float, tone: string, label: string}|null,
     *     ma: array{tone: string, key: string, label: string, short: string, long: string}|null,
     *     bollinger: array{tone: string, key: string, label: string, width: float, width_display: string, position: float}|null,
     *     pivot: array{pp: string, r1: string, r2: string, s1: string, s2: string}|null
     * }>
     */
    public function forSymbol(string $symbol): array
    {
        return $this->snapshot($symbol)['frames'];
    }

    /**
     * Live last price plus the six timeframe readings, from one scanner request.
     *
     * @return array{
     *     price: string|null,
     *     frames: list<array{
     *         label: string,
     *         rsi: array{value: float, display: string, width: float, tone: string, label: string}|null,
     *         macd: array{histogram: float, display: string, strength: float, tone: string, label: string}|null,
     *         ma: array{tone: string, key: string, label: string, short: string, long: string}|null,
     *         bollinger: array{tone: string, key: string, label: string, width: float, width_display: string, position: float}|null,
     *         pivot: array{pp: string, r1: string, r2: string, s1: string, s2: string}|null
     *     }>
     * }
     */
    public function snapshot(string $symbol): array
    {
        $ticker = self::SCANNER_SYMBOLS[$symbol] ?? null;

        if ($ticker === null) {
            return [
                'price' => null,
                'frames' => $this->emptyFrames(),
            ];
        }

        $values = $this->fetch($ticker, $this->columns());
        $frames = [];
        $liveClose = null;
        $index = 0;

        foreach (self::INTERVALS as $label => $suffix) {
            $close = $values[$index + 3] ?? null;

            if ($suffix === '') {
                $liveClose = $close;
            }

            $frames[] = [
                'label' => $label,
                'rsi' => $this->rsiReading($values[$index] ?? null),
                'macd' => $this->macdReading($values[$index + 1] ?? null, $values[$index + 2] ?? null),
                'ma' => $this->maReading(
                    $close,
                    $values[$index + 4] ?? null,
                    $values[$index + 5] ?? null,
                ),
                'bollinger' => $this->bollingerReading(
                    $label,
                    $close,
                    $values[$index + 6] ?? null,
                    $values[$index + 7] ?? null,
                    $values[$index + 8] ?? null,
                ),
                'pivot' => $this->classicPivot(
                    $values[$index + 9] ?? null,
                    $values[$index + 10] ?? null,
                    $values[$index + 11] ?? null,
                ),
            ];
            $index += self::COLUMNS_PER_INTERVAL;
        }

        return [
            'price' => $liveClose === null ? null : $this->formatPrice($liveClose),
            'frames' => $frames,
        ];
    }

    /**
     * @param  list<string>  $columns
     * @return list<float|null>
     */
    private function fetch(string $ticker, array $columns): array
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(6)
                ->acceptJson()
                ->post(self::ENDPOINT, [
                    'symbols' => ['tickers' => [$ticker]],
                    'columns' => $columns,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $row = $response->json('data.0.d');

        if (! is_array($row)) {
            return [];
        }

        return array_map(
            fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null,
            $row,
        );
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        $columns = [];

        foreach (self::INTERVALS as $suffix) {
            $interval = $suffix === '' ? '' : '|'.$suffix;
            $columns[] = 'RSI'.$interval;
            $columns[] = 'MACD.macd'.$interval;
            $columns[] = 'MACD.signal'.$interval;
            $columns[] = 'close'.$interval;
            $columns[] = 'EMA50'.$interval;
            $columns[] = 'EMA200'.$interval;
            $columns[] = 'BB.upper'.$interval;
            $columns[] = 'BB.lower'.$interval;
            $columns[] = 'BB.basis'.$interval;
            $columns[] = 'high[1]'.$interval;
            $columns[] = 'low[1]'.$interval;
            $columns[] = 'close[1]'.$interval;
        }

        return $columns;
    }

    /**
     * @return array{value: float, display: string, width: float, tone: string, label: string}|null
     */
    private function rsiReading(?float $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $rsi = max(0, min(100, $value));

        [$tone, $label] = match (true) {
            $rsi < 30 => ['buy', 'Buying Area / Oversold'],
            $rsi > 70 => ['sell', 'Selling Area / Overbought'],
            default => ['neutral', 'Neutral'],
        };

        return [
            'value' => $rsi,
            'display' => number_format($rsi, 1, '.', ''),
            'width' => $rsi,
            'tone' => $tone,
            'label' => $label,
        ];
    }

    /**
     * @return array{histogram: float, display: string, strength: float, tone: string, label: string}|null
     */
    private function macdReading(?float $macd, ?float $signal): ?array
    {
        if ($macd === null || $signal === null) {
            return null;
        }

        $histogram = $macd - $signal;
        $scale = max(abs($macd), abs($signal), 1e-12);
        $flat = abs($histogram) <= ($scale * 0.0001);

        [$tone, $label] = match (true) {
            $flat => ['neutral', 'Neutral'],
            $histogram > 0 => ['buy', 'Bullish'],
            default => ['sell', 'Bearish'],
        };

        return [
            'histogram' => $histogram,
            'display' => $this->formatHistogram($histogram),
            'strength' => $flat ? 0.0 : min(100, abs($histogram) / $scale * 100),
            'tone' => $tone,
            'label' => $label,
        ];
    }

    /**
     * @return array{tone: string, key: string, label: string, short: string, long: string}|null
     */
    private function maReading(?float $close, ?float $ema50, ?float $ema200): ?array
    {
        if ($close === null || $ema50 === null || $ema200 === null) {
            return null;
        }

        $spread = ($ema50 - $ema200) / max(abs($close), 1e-12);
        $flat = abs($spread) <= 0.0005;

        [$tone, $key, $label] = match (true) {
            $flat => ['neutral', 'neutral', 'Neutral'],
            $spread > 0 && $close >= $ema50 => ['buy', 'strong_buy', 'Strong Bullish'],
            $spread > 0 => ['buy', 'buy', 'Bullish Trend'],
            default => ['sell', 'sell', 'Bearish Trend'],
        };

        return [
            'tone' => $tone,
            'key' => $key,
            'label' => $label,
            'short' => $this->formatPrice($ema50),
            'long' => $this->formatPrice($ema200),
        ];
    }

    /**
     * @return array{tone: string, key: string, label: string, width: float, width_display: string, position: float}|null
     */
    private function bollingerReading(string $label, ?float $close, ?float $upper, ?float $lower, ?float $basis): ?array
    {
        if ($close === null || $upper === null || $lower === null || $basis === null) {
            return null;
        }

        $span = $upper - $lower;

        if ($span <= 0) {
            return null;
        }

        $width = ($span / max(abs($basis), 1e-12)) * 100;
        $position = max(0, min(100, (($close - $lower) / $span) * 100));
        $squeeze = self::BB_SQUEEZE[$label] ?? 2.0;
        $expand = self::BB_EXPAND[$label] ?? 6.0;

        [$tone, $key, $status] = match (true) {
            $width <= $squeeze => ['neutral', 'squeeze', 'Squeeze / Low Volatility'],
            $width >= $expand => ['sell', 'expand', 'Expanded / High Volatility'],
            default => ['buy', 'normal', 'Normal Volatility'],
        };

        return [
            'tone' => $tone,
            'key' => $key,
            'label' => $status,
            'width' => $width,
            'width_display' => number_format($width, 2, '.', '').'%',
            'position' => $position,
        ];
    }

    /**
     * Classic floor pivots from the previous completed candle.
     * These move when that candle closes, per timeframe.
     *
     * @return array{pp: string, r1: string, r2: string, s1: string, s2: string}|null
     */
    private function classicPivot(?float $high, ?float $low, ?float $close): ?array
    {
        if ($high === null || $low === null || $close === null || $high < $low) {
            return null;
        }

        $pivot = ($high + $low + $close) / 3;
        $range = $high - $low;

        return [
            'pp' => $this->formatPrice($pivot),
            'r1' => $this->formatPrice((2 * $pivot) - $low),
            'r2' => $this->formatPrice($pivot + $range),
            's1' => $this->formatPrice((2 * $pivot) - $high),
            's2' => $this->formatPrice($pivot - $range),
        ];
    }

    private function formatHistogram(float $value): string
    {
        $absolute = abs($value);
        $decimals = match (true) {
            $absolute >= 100 => 1,
            $absolute >= 1 => 2,
            $absolute >= 0.01 => 4,
            default => 6,
        };

        return number_format($value, $decimals, '.', '');
    }

    private function formatPrice(float $value): string
    {
        $absolute = abs($value);
        $decimals = match (true) {
            $absolute >= 1000 => 2,
            $absolute >= 100 => 2,
            $absolute >= 10 => 3,
            $absolute >= 1 => 4,
            default => 5,
        };

        return number_format($value, $decimals, '.', '');
    }

    /**
     * @return list<array{label: string, rsi: null, macd: null, ma: null, bollinger: null, pivot: null}>
     */
    private function emptyFrames(): array
    {
        $frames = [];

        foreach (array_keys(self::INTERVALS) as $label) {
            $frames[] = [
                'label' => $label,
                'rsi' => null,
                'macd' => null,
                'ma' => null,
                'bollinger' => null,
                'pivot' => null,
            ];
        }

        return $frames;
    }
}
