<?php

namespace Modules\Trading\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class TradingViewIndicatorReader
{
    private const ENDPOINT = 'https://scanner.tradingview.com/global/scan';

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
     * @return list<array{label: string, rsi: array{value: float, display: string, width: float, tone: string, label: string}|null, macd: array{histogram: float, display: string, strength: float, tone: string, label: string}|null}>
     */
    public function forSymbol(string $symbol): array
    {
        $ticker = self::SCANNER_SYMBOLS[$symbol] ?? null;

        if ($ticker === null) {
            return $this->emptyFrames();
        }

        $values = $this->fetch($ticker, $this->columns());
        $frames = [];
        $index = 0;

        foreach (self::INTERVALS as $label => $suffix) {
            $frames[] = [
                'label' => $label,
                'rsi' => $this->rsiReading($values[$index] ?? null),
                'macd' => $this->macdReading($values[$index + 1] ?? null, $values[$index + 2] ?? null),
            ];
            $index += 3;
        }

        return $frames;
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

    /**
     * @return list<array{label: string, rsi: null, macd: null}>
     */
    private function emptyFrames(): array
    {
        $frames = [];

        foreach (array_keys(self::INTERVALS) as $label) {
            $frames[] = [
                'label' => $label,
                'rsi' => null,
                'macd' => null,
            ];
        }

        return $frames;
    }
}
