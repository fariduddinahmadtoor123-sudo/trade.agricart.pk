<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;

class SmartAlertsPage extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string $translationKey = 'smart_alerts';

    protected static ?string $navigationLabel = 'Smart Alerts';

    protected static ?string $title = 'Smart Alerts';

    protected static ?int $navigationSort = 5;

    protected string $view = 'trading::filament.pages.smart-alerts';
}
