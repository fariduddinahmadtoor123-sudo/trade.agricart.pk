<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Services\TradingViewIndicatorReader;

class TradingView extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'signals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string $translationKey = 'signals';

    protected static ?string $navigationLabel = 'Signals';

    protected static ?string $title = 'Signals';

    protected static ?int $navigationSort = 1;

    protected string $view = 'trading::filament.pages.trading-view';

    /**
     * Sector shortcuts. Each button value is a TradingView symbol.
     *
     * @var list<array{key: string, label: string, icon: string, symbols: list<array{label: string, value: string}>}>
     */
    public array $sectors = [
        [
            'key' => 'currencies',
            'label' => 'Currencies',
            'icon' => 'heroicon-o-banknotes',
            'symbols' => [
                ['label' => 'EURUSD', 'value' => 'FX:EURUSD'],
                ['label' => 'USDJPY', 'value' => 'FX:USDJPY'],
                ['label' => 'GBPUSD', 'value' => 'FX:GBPUSD'],
            ],
        ],
        [
            'key' => 'indices',
            'label' => 'Indices',
            'icon' => 'heroicon-o-chart-bar',
            'symbols' => [
                ['label' => 'US500', 'value' => 'FOREXCOM:SPXUSD'],
                ['label' => 'US100', 'value' => 'FOREXCOM:NSXUSD'],
                ['label' => 'US30', 'value' => 'FOREXCOM:US30'],
            ],
        ],
        [
            'key' => 'commodities',
            'label' => 'Commodities',
            'icon' => 'heroicon-o-cube',
            'symbols' => [
                ['label' => 'XAUUSD', 'value' => 'OANDA:XAUUSD'],
                ['label' => 'USOIL', 'value' => 'FOREXCOM:USOIL'],
                ['label' => 'XAGUSD', 'value' => 'OANDA:XAGUSD'],
            ],
        ],
        [
            'key' => 'crypto',
            'label' => 'Crypto',
            'icon' => 'heroicon-o-circle-stack',
            'symbols' => [
                ['label' => 'BTCUSDT', 'value' => 'BINANCE:BTCUSDT'],
                ['label' => 'ETHUSDT', 'value' => 'BINANCE:ETHUSDT'],
                ['label' => 'SOLUSDT', 'value' => 'BINANCE:SOLUSDT'],
            ],
        ],
    ];

    public string $symbol = 'OANDA:XAUUSD';

    /**
     * Investing.com technical-summary pair for each TradingView symbol.
     * US30 is omitted because that widget has no Dow Jones instrument.
     *
     * @var array<string, array{group: string, id: int}>
     */
    public array $investingPairs = [
        'OANDA:XAUUSD' => ['group' => 'forex', 'id' => 68],
        'OANDA:XAGUSD' => ['group' => 'forex', 'id' => 69],
        'FOREXCOM:USOIL' => ['group' => 'commodities', 'id' => 8849],
        'FX:EURUSD' => ['group' => 'forex', 'id' => 1],
        'FX:GBPUSD' => ['group' => 'forex', 'id' => 2],
        'FX:USDJPY' => ['group' => 'forex', 'id' => 3],
        'BINANCE:BTCUSDT' => ['group' => 'stocks', 'id' => 945629],
    ];

    /**
     * Myfxbook Community Outlook symbol IDs for the official embed.
     * Covers majors + gold/silver. Crude oil is not in Myfxbook's outlook list.
     * TradingView has no official long/short sentiment embed (only Technical Analysis gauges).
     *
     * @var array<string, int>
     */
    public array $myfxbookSentimentSymbols = [
        'FX:EURUSD' => 1,
        'FX:GBPUSD' => 2,
        'FX:USDJPY' => 3,
        'OANDA:XAUUSD' => 51,
        'OANDA:XAGUSD' => 50,
    ];

    /**
     * Signal gauges, shortest beside the sidebar and longest at the far side.
     *
     * @var list<array{label: string, interval: string}>
     */
    public array $gauges = [
        ['label' => '30m', 'interval' => '30m'],
        ['label' => '1H', 'interval' => '1h'],
        ['label' => '4H', 'interval' => '4h'],
        ['label' => '1D', 'interval' => '1D'],
        ['label' => '1W', 'interval' => '1W'],
        ['label' => '1M', 'interval' => '1M'],
    ];

    /**
     * Investing.com summary boxes, shortest beside the sidebar.
     * Intervals published by that widget: 15m, 30m, 1H, 5H, daily, weekly.
     *
     * @var list<array{label: string, timeframe: string}>
     */
    public array $investingFrames = [
        ['label' => '15m', 'timeframe' => '900'],
        ['label' => '30m', 'timeframe' => '1800'],
        ['label' => '1H', 'timeframe' => '3600'],
        ['label' => '5H', 'timeframe' => '18000'],
        ['label' => '1D', 'timeframe' => '86400'],
        ['label' => '1W', 'timeframe' => 'week'],
    ];

    /**
     * Indicator readings for the six summary timeframes.
     *
     * @var list<array<string, mixed>>
     */
    public array $indicatorFrames = [];

    public ?string $livePrice = null;

    public function mount(): void
    {
        $default = (string) config('trading.default_tradingview_symbol', 'OANDA:XAUUSD');

        $this->symbol = in_array($default, $this->allowedSymbols(), true) ? $default : 'OANDA:XAUUSD';
        $this->loadIndicators();
    }

    public function updatedSymbol(string $value): void
    {
        if (! in_array($value, $this->allowedSymbols(), true)) {
            $this->symbol = 'OANDA:XAUUSD';
        }

        $this->loadIndicators();
    }

    public function refreshIndicators(): void
    {
        $this->loadIndicators();
    }

    private function loadIndicators(): void
    {
        $snapshot = app(TradingViewIndicatorReader::class)->snapshot($this->symbol);
        $this->indicatorFrames = $snapshot['frames'];
        $this->livePrice = $snapshot['price'];
    }

    /**
     * Short labels for the TradingView attribution line.
     *
     * @return array<string, string>
     */
    public function symbolLabels(): array
    {
        $labels = [];

        foreach ($this->sectors as $sector) {
            foreach ($sector['symbols'] as $symbol) {
                $labels[$symbol['value']] = $symbol['label'];
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function allowedSymbols(): array
    {
        return array_keys($this->symbolLabels());
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-page-trading-view'];
    }
}
