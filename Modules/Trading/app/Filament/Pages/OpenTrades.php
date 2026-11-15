<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;

class OpenTrades extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'open-trades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string $translationKey = 'open_trades';

    protected static ?string $navigationLabel = 'Open Trades';

    protected static ?string $title = 'Open Trades';

    protected static ?int $navigationSort = 4;

    protected string $view = 'trading::filament.pages.open-trades';
}
