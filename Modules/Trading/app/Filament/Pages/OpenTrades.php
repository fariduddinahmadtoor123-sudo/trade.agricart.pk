<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Models\TerminalOrder;

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

    /**
     * @return Collection<int, TerminalOrder>
     */
    public function positions(): Collection
    {
        return TerminalOrder::query()
            ->where('status', TerminalOrderStatus::Open)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }
}
