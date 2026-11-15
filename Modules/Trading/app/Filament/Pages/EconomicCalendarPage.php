<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Services\HighImpactUsdEventSchedule;

class EconomicCalendarPage extends Page
{
    use HasCleanTradingPage;

    /**
     * Investing.com country IDs used by their official calendar embed.
     *
     * @var array<string, int>
     */
    private const INVESTING_SIGNAL_CURRENCIES = [
        'USD' => 5,
        'EUR' => 72,
        'GBP' => 4,
        'JPY' => 35,
    ];

    /**
     * Full country set from Investing.com's official economic-calendar widget.
     *
     * @var list<int>
     */
    private const INVESTING_ALL_COUNTRIES = [
        25, 32, 6, 37, 72, 22, 17, 39, 14, 10, 35, 43, 56, 36, 110, 11, 26, 12, 4, 5,
    ];

    /**
     * Investing.com embed timezone IDs keyed by GMT offset hours.
     *
     * @var array<string, int>
     */
    private const INVESTING_TIMEZONES_BY_OFFSET = [
        '-11' => 2,
        '-10' => 3,
        '-8' => 4,
        '-7' => 5,
        '-6' => 6,
        '-5' => 8,
        '-4' => 9,
        '-3.5' => 11,
        '-3' => 10,
        '-2' => 50,
        '-1' => 53,
        '0' => 14,
        '1' => 15,
        '2' => 16,
        '3' => 17,
        '3.5' => 19,
        '4' => 20,
        '4.5' => 21,
        '5' => 22,
        '5.5' => 23,
        '5.75' => 24,
        '6' => 25,
        '6.5' => 26,
        '7' => 27,
        '8' => 28,
        '9' => 29,
        '9.5' => 30,
        '10' => 91,
        '11' => 31,
        '12' => 1,
        '13' => 33,
    ];

    /**
     * Official Investing.com embed range: day (today) or week.
     */
    public string $investingRange = 'day';

    /**
     * Investing.com importance values: 1 = low, 2 = medium, 3 = high.
     *
     * @var list<int>
     */
    public array $investingImpacts = [1, 2, 3];

    public bool $investingAllImpacts = true;

    /**
     * Currency filters mapped from the Signals page markets.
     *
     * @var list<string>
     */
    public array $investingCurrencies = [];

    public bool $investingAllCurrencies = true;

    /**
     * Investing.com widget timezone ID. Defaults to the app timezone, then
     * syncs to the visitor's system timezone from the browser.
     */
    public int $investingTimeZone = 14;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string $translationKey = 'economic_calendar';

    protected static ?string $navigationLabel = 'Economic Calendar / News';

    protected static ?string $title = 'Economic Calendar / News';

    protected static ?int $navigationSort = 2;

    protected string $view = 'trading::filament.pages.economic-calendar';

    public function mount(): void
    {
        $this->investingTimeZone = $this->investingTimeZoneFromOffset(
            now(config('app.timezone'))->offsetHours,
        );
    }

    /**
     * Sync the Investing widget clock to the visitor's system timezone.
     */
    public function syncInvestingTimeZoneFromBrowser(float $offsetHours): void
    {
        $this->investingTimeZone = $this->investingTimeZoneFromOffset($offsetHours);
    }

    public function setInvestingRange(string $range): void
    {
        if (! in_array($range, ['day', 'week'], true)) {
            return;
        }

        $this->investingRange = $range;
    }

    public function enableInvestingAllImpacts(): void
    {
        $this->investingAllImpacts = true;
        $this->investingImpacts = [1, 2, 3];
    }

    /**
     * Select one impact level. Investing.com uses 1 = low, 2 = medium, 3 = high.
     */
    public function selectInvestingImpact(string $impact): void
    {
        $map = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
        ];

        if (! isset($map[$impact])) {
            return;
        }

        $this->investingAllImpacts = false;
        $this->investingImpacts = [$map[$impact]];
    }

    /**
     * Dropdown handler: keep All as the default, never force High.
     */
    public function setInvestingImpactFilter(string $impact): void
    {
        if ($impact === 'all') {
            $this->enableInvestingAllImpacts();

            return;
        }

        $this->selectInvestingImpact($impact);
    }

    public function enableInvestingAllCurrencies(): void
    {
        $this->investingAllCurrencies = true;
        $this->investingCurrencies = [];
    }

    /**
     * Select a single Signals currency, or fall back to All.
     */
    public function selectInvestingCurrency(string $currency): void
    {
        if (! array_key_exists($currency, self::INVESTING_SIGNAL_CURRENCIES)) {
            return;
        }

        $this->investingAllCurrencies = false;
        $this->investingCurrencies = [$currency];
    }

    /**
     * Dropdown handler for currency filters.
     */
    public function setInvestingCurrencyFilter(string $currency): void
    {
        if ($currency === 'all') {
            $this->enableInvestingAllCurrencies();

            return;
        }

        $this->selectInvestingCurrency($currency);
    }

    public function selectedInvestingImpactFilter(): string
    {
        if ($this->investingAllImpacts || $this->investingImpacts === []) {
            return 'all';
        }

        return match ($this->investingImpacts[0] ?? null) {
            3 => 'high',
            2 => 'medium',
            1 => 'low',
            default => 'all',
        };
    }

    public function selectedInvestingCurrencyFilter(): string
    {
        if ($this->investingAllCurrencies || $this->investingCurrencies === []) {
            return 'all';
        }

        return $this->investingCurrencies[0] ?? 'all';
    }

    /**
     * Currencies that match the Signals page markets and map to Investing country IDs.
     *
     * @return list<string>
     */
    public function investingCurrencyOptions(): array
    {
        return array_keys(self::INVESTING_SIGNAL_CURRENCIES);
    }

    /**
     * Official Investing.com economic calendar embed. Only documented widget
     * query parameters are used — no scraping or unofficial APIs.
     */
    public function investingCalendarUrl(): string
    {
        $impacts = $this->investingAllImpacts || $this->investingImpacts === []
            ? [1, 2, 3]
            : $this->investingImpacts;

        // Datepicker and filters are Investing's own controls. Timezone stays
        // off that chrome; the page clock syncs timeZone for the visitor.
        return 'https://sslecal2.investing.com?'
            .'columns=exc_flags,exc_currency,exc_importance,exc_actual,exc_forecast,exc_previous'
            .'&features=datepicker,filters'
            .'&countries='.implode(',', $this->investingCountryIds())
            .'&importance='.implode(',', $impacts)
            .'&calType='.$this->investingRange
            .'&timeZone='.$this->investingTimeZone
            .'&lang=1';
    }

    /**
     * Next high-impact release for the countdown card, scoped to active filters.
     *
     * @return array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, is_today: bool, when: string}|null
     */
    public function nextHighImpactUsdEvent(): ?array
    {
        return $this->highImpactUsdEventSchedule()->next(filters: $this->highImpactFilterState());
    }

    /**
     * Today's primary high-impact release banner, when one lands today.
     *
     * @return array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, is_today: bool, when: string}|null
     */
    public function todaysHighImpactUsdHighlight(): ?array
    {
        return $this->highImpactUsdEventSchedule()->todaysHighlight(filters: $this->highImpactFilterState());
    }

    /**
     * Compact upcoming list for the left highlights rail.
     *
     * @return list<array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, is_today: bool, when: string}>
     */
    public function upcomingHighImpactUsdEvents(): array
    {
        return $this->highImpactUsdEventSchedule()->upcoming(3, filters: $this->highImpactFilterState())->all();
    }

    /**
     * Left-rail countdown tracks curated USD data releases only (FOMC/NFP/CPI/GDP/Retail).
     * Investing "All" currencies still includes Speeches for other pairs; those stay in the
     * widget and are not treated as the next USD data print.
     *
     * @return array{currencies: list<string>, impacts: list<int>, range: string}
     */
    private function highImpactFilterState(): array
    {
        $selectedCurrency = $this->selectedInvestingCurrencyFilter();
        $selectedImpact = $this->selectedInvestingImpactFilter();

        return [
            // Card is USD-only. "All" maps to USD so Speeches (EUR/etc.) do not desync it.
            'currencies' => $selectedCurrency === 'all' ? ['USD'] : [$selectedCurrency],
            // "High-Impact" card: All impact still means high data prints, not Speeches.
            'impacts' => match ($selectedImpact) {
                'medium' => [2],
                'low' => [1],
                default => [3],
            },
            'range' => $this->investingRange,
        ];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-page-economic-calendar'];
    }

    private function highImpactUsdEventSchedule(): HighImpactUsdEventSchedule
    {
        return app(HighImpactUsdEventSchedule::class);
    }

    private function investingTimeZoneFromOffset(float $offsetHours): int
    {
        $key = $this->offsetKey($offsetHours);

        return self::INVESTING_TIMEZONES_BY_OFFSET[$key]
            ?? self::INVESTING_TIMEZONES_BY_OFFSET['0'];
    }

    private function offsetKey(float $offsetHours): string
    {
        $normalized = round($offsetHours * 4) / 4;

        if (fmod($normalized, 1.0) === 0.0) {
            return (string) (int) $normalized;
        }

        return rtrim(rtrim(number_format($normalized, 2, '.', ''), '0'), '.');
    }

    /**
     * @return list<int>
     */
    private function investingCountryIds(): array
    {
        if ($this->investingAllCurrencies || $this->investingCurrencies === []) {
            return self::INVESTING_ALL_COUNTRIES;
        }

        $ids = [];

        foreach ($this->investingCurrencies as $currency) {
            if (isset(self::INVESTING_SIGNAL_CURRENCIES[$currency])) {
                $ids[] = self::INVESTING_SIGNAL_CURRENCIES[$currency];
            }
        }

        $ids = array_values(array_unique($ids));

        return $ids !== [] ? $ids : self::INVESTING_ALL_COUNTRIES;
    }
}
