<?php

namespace Modules\Trading\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class HighImpactUsdEventSchedule
{
    private const TIMEZONE = 'America/New_York';

    /**
     * Official FOMC decision days (statement at 14:00 ET).
     * Source: federalreserve.gov monetary policy calendars.
     *
     * @var list<string>
     */
    private const FOMC_DECISION_DAYS = [
        '2026-12-09',
        '2027-01-27',
        '2027-03-17',
        '2027-04-28',
        '2027-06-09',
        '2027-07-28',
        '2027-09-15',
        '2027-11-17',
    ];

    /**
     * @param  array{currencies?: list<string>, impacts?: list<int>, range?: string}  $filters
     * @return array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, when: string, is_today: bool}|null
     */
    public function next(?CarbonImmutable $now = null, array $filters = []): ?array
    {
        return $this->upcoming(1, $now, $filters, ignoreRangeWindow: true)->first();
    }

    /**
     * @param  array{currencies?: list<string>, impacts?: list<int>, range?: string}  $filters
     * @return array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, when: string, is_today: bool}|null
     */
    public function todaysHighlight(?CarbonImmutable $now = null, array $filters = []): ?array
    {
        $now = $this->now($now);

        return $this->upcoming(12, $now, $filters, ignoreRangeWindow: true)
            ->first(fn (array $event): bool => $event['is_today']);
    }

    /**
     * @param  array{currencies?: list<string>, impacts?: list<int>, range?: string}  $filters
     * @return Collection<int, array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, iso: string, when: string, is_today: bool}>
     */
    public function upcoming(
        int $limit = 3,
        ?CarbonImmutable $now = null,
        array $filters = [],
        bool $ignoreRangeWindow = false,
    ): Collection {
        $now = $this->now($now);
        $currencies = array_values(array_filter($filters['currencies'] ?? []));
        $impacts = array_values(array_filter($filters['impacts'] ?? [1, 2, 3]));
        $range = ($filters['range'] ?? 'day') === 'week' ? 'week' : 'day';

        $events = $this->schedule($now)
            ->filter(fn (array $event): bool => $event['at']->greaterThan($now))
            ->filter(fn (array $event): bool => $this->matchesCurrency($event, $currencies))
            ->filter(fn (array $event): bool => $this->matchesImpact($event, $impacts))
            ->sortBy(fn (array $event) => $event['at']->timestamp)
            ->values();

        if (! $ignoreRangeWindow) {
            $windowEnd = $range === 'week'
                ? $now->addDays(7)->endOfDay()
                : $now->endOfDay();

            $inWindow = $events->filter(
                fn (array $event): bool => $event['at']->lessThanOrEqualTo($windowEnd),
            )->values();

            if ($inWindow->isNotEmpty()) {
                $events = $inWindow;
            }
        }

        return $events
            ->take($limit)
            ->map(function (array $event) use ($now): array {
                $at = $event['at'];

                return [
                    'key' => $event['key'],
                    'name' => __('trading.calendar.usd_reference.items.'.$event['key'].'.name'),
                    'currency' => $event['currency'],
                    'impact' => $event['impact'],
                    'at' => $at,
                    'iso' => $at->toIso8601String(),
                    'when' => $at->format('D, M j · g:i A T'),
                    'is_today' => $at->isSameDay($now),
                ];
            });
    }

    /**
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function schedule(CarbonImmutable $now): Collection
    {
        return collect()
            ->concat($this->nfpDates($now))
            ->concat($this->cpiDates($now))
            ->concat($this->retailDates($now))
            ->concat($this->gdpDates($now))
            ->concat($this->fomcDates())
            ->unique(fn (array $event): string => $event['key'].'|'.$event['at']->timestamp)
            ->values();
    }

    /**
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function nfpDates(CarbonImmutable $now): Collection
    {
        $start = $now->startOfMonth();

        return collect(range(0, 8))->map(fn (int $offset): array => $this->event(
            'nfp',
            $this->nthWeekdayOnOrAfter($start->addMonths($offset)->startOfMonth(), CarbonImmutable::FRIDAY, 1)->setTime(8, 30),
        ));
    }

    /**
     * Approximate BLS CPI print: Wednesday on/after the 10th, 08:30 ET.
     *
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function cpiDates(CarbonImmutable $now): Collection
    {
        $start = $now->startOfMonth();

        return collect(range(0, 8))->map(function (int $offset) use ($start): array {
            $month = $start->addMonths($offset)->startOfMonth();
            $anchor = $month->day(10);

            return $this->event(
                'cpi',
                $this->weekdayOnOrAfter($anchor, CarbonImmutable::WEDNESDAY)->setTime(8, 30),
            );
        });
    }

    /**
     * Approximate Census Retail Sales: Tuesday on/after the 14th, 08:30 ET.
     *
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function retailDates(CarbonImmutable $now): Collection
    {
        $start = $now->startOfMonth();

        return collect(range(0, 8))->map(function (int $offset) use ($start): array {
            $month = $start->addMonths($offset)->startOfMonth();
            $anchor = $month->day(14);

            return $this->event(
                'retail',
                $this->weekdayOnOrAfter($anchor, CarbonImmutable::TUESDAY)->setTime(8, 30),
            );
        });
    }

    /**
     * Approximate GDP advance: last Thursday of Jan/Apr/Jul/Oct, 08:30 ET.
     *
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function gdpDates(CarbonImmutable $now): Collection
    {
        $months = [1, 4, 7, 10];
        $years = [$now->year, $now->year + 1];

        return collect($years)
            ->flatMap(fn (int $year) => collect($months)->map(
                fn (int $month): array => $this->event(
                    'gdp',
                    $this->lastWeekdayOfMonth($year, $month, CarbonImmutable::THURSDAY)->setTime(8, 30),
                ),
            ));
    }

    /**
     * @return Collection<int, array{key: string, currency: string, impact: int, at: CarbonImmutable}>
     */
    private function fomcDates(): Collection
    {
        return collect(self::FOMC_DECISION_DAYS)->map(
            fn (string $day): array => $this->event(
                'fomc',
                CarbonImmutable::parse($day.' 14:00:00', self::TIMEZONE),
            ),
        );
    }

    /**
     * @return array{key: string, currency: string, impact: int, at: CarbonImmutable}
     */
    private function event(string $key, CarbonImmutable $at): array
    {
        return [
            'key' => $key,
            'currency' => 'USD',
            'impact' => 3,
            'at' => $at,
        ];
    }

    /**
     * @param  array{currency: string}  $event
     * @param  list<string>  $currencies
     */
    private function matchesCurrency(array $event, array $currencies): bool
    {
        return $currencies === [] || in_array($event['currency'], $currencies, true);
    }

    /**
     * @param  array{impact: int}  $event
     * @param  list<int>  $impacts
     */
    private function matchesImpact(array $event, array $impacts): bool
    {
        return $impacts === [] || in_array($event['impact'], $impacts, true);
    }

    private function weekdayOnOrAfter(CarbonImmutable $date, int $weekday): CarbonImmutable
    {
        $day = $date;

        while ($day->dayOfWeek !== $weekday) {
            $day = $day->addDay();
        }

        return $day;
    }

    private function nthWeekdayOnOrAfter(CarbonImmutable $monthStart, int $weekday, int $nth): CarbonImmutable
    {
        $day = $this->weekdayOnOrAfter($monthStart, $weekday);

        return $day->addWeeks($nth - 1);
    }

    private function lastWeekdayOfMonth(int $year, int $month, int $weekday): CarbonImmutable
    {
        $day = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TIMEZONE)->endOfMonth()->startOfDay();

        while ($day->dayOfWeek !== $weekday) {
            $day = $day->subDay();
        }

        return $day;
    }

    private function now(?CarbonImmutable $now): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now(self::TIMEZONE))->timezone(self::TIMEZONE);
    }
}
