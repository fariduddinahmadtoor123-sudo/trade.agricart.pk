<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;

class Mt5Terminal extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'mt5-terminal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string $translationKey = 'mt5_terminal';

    protected static ?string $navigationLabel = 'MT5 Terminal';

    protected static ?string $title = 'MT5 Terminal';

    protected static ?int $navigationSort = 3;

    protected string $view = 'trading::filament.pages.mt5-terminal';
}
