<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Services\MarketSessionSchedule;

class MarketSessionsPage extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'sessions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string $translationKey = 'market_sessions';

    protected static ?string $navigationLabel = 'Market Sessions';

    protected static ?string $title = 'Market Sessions';

    protected static ?int $navigationSort = 3;

    protected string $view = 'trading::filament.pages.market-sessions';

    /**
     * @return list<array<string, mixed>>
     */
    public function sessionCards(): array
    {
        return $this->marketSessionSchedule()->deskCards();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timelineSessions(): array
    {
        return $this->marketSessionSchedule()->timelineSessions();
    }

    /**
     * Compact Alpine payload (open/close checks stay in UTC; bars are PKT-positioned in Blade).
     *
     * @return array{sessions: list<array{key: string, name: string, open_utc: int, close_utc: int}>}
     */
    public function timelineConfig(): array
    {
        $sessions = collect($this->timelineSessions())
            ->map(fn (array $session): array => [
                'key' => $session['key'],
                'name' => $session['name'],
                'open_utc' => $session['open_utc'],
                'close_utc' => $session['close_utc'],
            ])
            ->all();

        return [
            'sessions' => $sessions,
        ];
    }

    /**
     * @return array{
     *     local_clock: string,
     *     local_clock_short: string,
     *     now_percent: float,
     *     current_pkt_hour: int,
     *     forex_week_open: bool,
     *     active_keys: list<string>,
     *     active_names: list<string>,
     *     overlap_status: string,
     *     track_label: string
     * }
     */
    public function clockSnapshot(): array
    {
        return $this->marketSessionSchedule()->clockSnapshot();
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-page-market-sessions'];
    }

    private function marketSessionSchedule(): MarketSessionSchedule
    {
        return app(MarketSessionSchedule::class);
    }
}
