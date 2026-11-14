<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;

class EconomicCalendarPage extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string $translationKey = 'economic_calendar';

    protected static ?string $navigationLabel = 'Economic Calendar / News';

    protected static ?string $title = 'Economic Calendar / News';

    protected static ?int $navigationSort = 4;

    protected string $view = 'trading::filament.pages.economic-calendar';
}
