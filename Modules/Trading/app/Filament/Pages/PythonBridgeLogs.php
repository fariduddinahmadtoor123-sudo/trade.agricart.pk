<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Models\TradingLog;

class PythonBridgeLogs extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'python-bridge-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string $translationKey = 'python_bridge_logs';

    protected static ?string $navigationLabel = 'Python Bridge Logs';

    protected static ?string $title = 'Python Bridge Logs';

    protected static ?int $navigationSort = 4;

    protected string $view = 'trading::filament.pages.python-bridge-logs';

    /**
     * @var list<TradingLog>
     */
    public array $recentLogs = [];

    public function mount(): void
    {
        $this->refreshLogs();
    }

    public function refreshLogs(): void
    {
        $this->recentLogs = TradingLog::query()
            ->latestFeed()
            ->limit(50)
            ->get()
            ->all();
    }
}
