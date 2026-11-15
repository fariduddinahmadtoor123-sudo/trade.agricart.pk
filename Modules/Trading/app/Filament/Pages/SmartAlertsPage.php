<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Services\SmartAlertFeed;
use Modules\Trading\Services\SmartAlertPreferences;

class SmartAlertsPage extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string $translationKey = 'smart_alerts';

    protected static ?string $navigationLabel = 'Smart Alerts';

    protected static ?string $title = 'Smart Alerts';

    protected static ?int $navigationSort = 6;

    protected string $view = 'trading::filament.pages.smart-alerts';

    public bool $highImpactNews = true;

    public bool $warn15 = true;

    public bool $warn30 = true;

    /**
     * @var list<string>
     */
    public array $currencies = ['USD'];

    public function mount(SmartAlertPreferences $preferences): void
    {
        $this->applyPreferences($preferences->forUser((int) auth()->id()));
    }

    public function toggleHighImpactNews(): void
    {
        $this->highImpactNews = ! $this->highImpactNews;
        $this->persistPreferences();
    }

    public function toggleWarn15(): void
    {
        $this->warn15 = ! $this->warn15;
        $this->persistPreferences();
    }

    public function toggleWarn30(): void
    {
        $this->warn30 = ! $this->warn30;
        $this->persistPreferences();
    }

    public function toggleCurrency(string $currency): void
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, SmartAlertPreferences::CURRENCIES, true)) {
            return;
        }

        if (in_array($currency, $this->currencies, true)) {
            $this->currencies = array_values(array_filter(
                $this->currencies,
                fn (string $item): bool => $item !== $currency,
            ));
        } else {
            $this->currencies[] = $currency;
            $this->currencies = array_values(array_unique($this->currencies));
        }

        if ($this->currencies === []) {
            $this->currencies = ['USD'];
        }

        $this->persistPreferences();
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     enabled: bool,
     *     action: string
     * }>
     */
    public function alertRules(): array
    {
        return [
            [
                'key' => 'high_impact_news',
                'label' => __('trading.smart_alerts.rules.high_impact_news.label'),
                'description' => __('trading.smart_alerts.rules.high_impact_news.description'),
                'enabled' => $this->highImpactNews,
                'action' => 'toggleHighImpactNews',
            ],
            [
                'key' => 'warn_30',
                'label' => __('trading.smart_alerts.rules.warn_30.label'),
                'description' => __('trading.smart_alerts.rules.warn_30.description'),
                'enabled' => $this->warn30,
                'action' => 'toggleWarn30',
            ],
            [
                'key' => 'warn_15',
                'label' => __('trading.smart_alerts.rules.warn_15.label'),
                'description' => __('trading.smart_alerts.rules.warn_15.description'),
                'enabled' => $this->warn15,
                'action' => 'toggleWarn15',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function currencyOptions(): array
    {
        return SmartAlertPreferences::CURRENCIES;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activityFeed(): array
    {
        return app(SmartAlertFeed::class)->activity($this->preferenceState());
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-page-smart-alerts'];
    }

    /**
     * @param  array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }  $preferences
     */
    private function applyPreferences(array $preferences): void
    {
        $this->highImpactNews = $preferences['high_impact_news'];
        $this->warn15 = $preferences['warn_15'];
        $this->warn30 = $preferences['warn_30'];
        $this->currencies = $preferences['currencies'];
    }

    private function persistPreferences(): void
    {
        $preferences = app(SmartAlertPreferences::class)->update(
            (int) auth()->id(),
            $this->preferenceState(),
        );

        $this->applyPreferences($preferences);

        Notification::make()
            ->title(__('trading.smart_alerts.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }
     */
    private function preferenceState(): array
    {
        return [
            'high_impact_news' => $this->highImpactNews,
            'warn_15' => $this->warn15,
            'warn_30' => $this->warn30,
            'currencies' => $this->currencies,
        ];
    }
}
