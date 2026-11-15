<?php

namespace Modules\Trading\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class MarketSessionSchedule
{
    /**
     * Pakistan Standard Time offset from UTC in minutes (no DST).
     */
    public const PKT_OFFSET_MINUTES = 5 * 60;

    /**
     * Forex sessions in UTC minutes from midnight. Ranges may wrap past 24:00.
     * Colors follow a risk ladder: green (low) → amber → orange → blood red (high).
     *
     * @return list<array{
     *     key: string,
     *     open_utc: int,
     *     close_utc: int,
     *     activity: string,
     *     color: string
     * }>
     */
    public function sessions(): array
    {
        return [
            [
                'key' => 'sydney',
                'open_utc' => 22 * 60,
                'close_utc' => 7 * 60,
                'activity' => 'low',
                'color' => '#15803d',
            ],
            [
                'key' => 'tokyo',
                'open_utc' => 0,
                'close_utc' => 9 * 60,
                'activity' => 'moderate',
                'color' => '#ca8a04',
            ],
            [
                'key' => 'london',
                'open_utc' => 8 * 60,
                'close_utc' => 17 * 60,
                'activity' => 'high',
                'color' => '#c2410c',
            ],
            [
                'key' => 'new_york',
                'open_utc' => 13 * 60,
                'close_utc' => 22 * 60,
                'activity' => 'high',
                'color' => '#991b1b',
            ],
        ];
    }

    /**
     * Shift a UTC minute-of-day into PKT minute-of-day.
     */
    public function toPktMinutes(int $utcMinutes): int
    {
        return ($utcMinutes + self::PKT_OFFSET_MINUTES) % 1440;
    }

    /**
     * @return list<array{left: float, width: float}>
     */
    public function segmentsForWindow(int $openMinutes, int $closeMinutes): array
    {
        if ($openMinutes === $closeMinutes) {
            return [];
        }

        if ($openMinutes < $closeMinutes) {
            return [[
                'left' => ($openMinutes / 1440) * 100,
                'width' => (($closeMinutes - $openMinutes) / 1440) * 100,
            ]];
        }

        return [
            [
                'left' => ($openMinutes / 1440) * 100,
                'width' => ((1440 - $openMinutes) / 1440) * 100,
            ],
            [
                'left' => 0.0,
                'width' => ($closeMinutes / 1440) * 100,
            ],
        ];
    }

    /**
     * Timeline rows positioned on the PKT day axis.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     open_utc: int,
     *     close_utc: int,
     *     open_pkt: int,
     *     close_pkt: int,
     *     activity: string,
     *     color: string,
     *     segments: list<array{left: float, width: float}>
     * }>
     */
    public function timelineSessions(): array
    {
        return collect($this->sessions())
            ->map(function (array $session): array {
                $openPkt = $this->toPktMinutes($session['open_utc']);
                $closePkt = $this->toPktMinutes($session['close_utc']);

                return [
                    'key' => $session['key'],
                    'name' => __('trading.market_sessions.sessions.'.$session['key'].'.name'),
                    'open_utc' => $session['open_utc'],
                    'close_utc' => $session['close_utc'],
                    'open_pkt' => $openPkt,
                    'close_pkt' => $closePkt,
                    'activity' => $session['activity'],
                    'color' => $session['color'],
                    'segments' => $this->segmentsForWindow($openPkt, $closePkt),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     hours_label: string,
     *     open_utc: int,
     *     close_utc: int,
     *     activity: string,
     *     activity_label: string,
     *     summary: string,
     *     traits: list<string>,
     *     color: string
     * }>
     */
    public function deskCards(): array
    {
        return collect($this->sessions())
            ->map(function (array $session): array {
                $key = $session['key'];
                $openPkt = $this->toPktMinutes($session['open_utc']);
                $closePkt = $this->toPktMinutes($session['close_utc']);

                return [
                    'key' => $key,
                    'name' => __('trading.market_sessions.sessions.'.$key.'.name'),
                    'hours_label' => $this->formatPktHours($openPkt, $closePkt),
                    'open_utc' => $session['open_utc'],
                    'close_utc' => $session['close_utc'],
                    'activity' => $session['activity'],
                    'activity_label' => __('trading.market_sessions.activity.'.$session['activity']),
                    'summary' => __('trading.market_sessions.sessions.'.$key.'.summary'),
                    'traits' => array_values((array) __('trading.market_sessions.sessions.'.$key.'.traits')),
                    'color' => $session['color'],
                ];
            })
            ->all();
    }

    /**
     * Forex cash week: closed Fri 22:00 UTC through Sun 22:00 UTC.
     */
    public function isForexWeekOpen(?CarbonInterface $now = null): bool
    {
        $utc = Carbon::instance($now ?? now())->utc();
        $day = (int) $utc->dayOfWeek;
        $minutes = ($utc->hour * 60) + $utc->minute;
        $fridayClose = 22 * 60;
        $sundayOpen = 22 * 60;

        if ($day === Carbon::SATURDAY) {
            return false;
        }

        if ($day === Carbon::SUNDAY) {
            return $minutes >= $sundayOpen;
        }

        if ($day === Carbon::FRIDAY) {
            return $minutes < $fridayClose;
        }

        return true;
    }

    public function isSessionOpen(float $utcMinutes, int $openUtc, int $closeUtc): bool
    {
        if ($openUtc === $closeUtc) {
            return false;
        }

        if ($openUtc < $closeUtc) {
            return $utcMinutes >= $openUtc && $utcMinutes < $closeUtc;
        }

        return $utcMinutes >= $openUtc || $utcMinutes < $closeUtc;
    }

    /**
     * Server snapshot so the needle, weekend banner, and active lanes render before Alpine boots.
     *
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
    public function clockSnapshot(?CarbonInterface $now = null): array
    {
        $utc = Carbon::instance($now ?? now())->utc();
        $pkt = $utc->copy()->timezone('Asia/Karachi');
        $minutes = ($pkt->hour * 60) + $pkt->minute + ($pkt->second / 60);
        $forexWeekOpen = $this->isForexWeekOpen($utc);
        $activeKeys = [];
        $activeNames = [];

        if ($forexWeekOpen) {
            $utcMinutes = ($utc->hour * 60) + $utc->minute + ($utc->second / 60);

            foreach ($this->timelineSessions() as $session) {
                if ($this->isSessionOpen($utcMinutes, $session['open_utc'], $session['close_utc'])) {
                    $activeKeys[] = $session['key'];
                    $activeNames[] = $session['name'];
                }
            }
        }

        return [
            'local_clock' => $pkt->format('H:i:s'),
            'local_clock_short' => $pkt->format('H:i'),
            'now_percent' => round(($minutes / 1440) * 100, 4),
            'current_pkt_hour' => $pkt->hour,
            'forex_week_open' => $forexWeekOpen,
            'active_keys' => $activeKeys,
            'active_names' => $activeNames,
            'overlap_status' => $this->overlapStatusLabel($forexWeekOpen, $activeNames),
            'track_label' => $this->trackLabel($forexWeekOpen, $activeNames),
        ];
    }

    /**
     * @param  list<string>  $activeNames
     */
    private function overlapStatusLabel(bool $forexWeekOpen, array $activeNames): string
    {
        if (! $forexWeekOpen) {
            return __('trading.market_sessions.status_closed');
        }

        if ($activeNames === []) {
            return __('trading.market_sessions.status_idle');
        }

        if (count($activeNames) === 1) {
            return __('trading.market_sessions.status_single', ['name' => $activeNames[0]]);
        }

        return __('trading.market_sessions.status_overlap', [
            'count' => count($activeNames),
            'names' => implode(' · ', $activeNames),
        ]);
    }

    /**
     * @param  list<string>  $activeNames
     */
    private function trackLabel(bool $forexWeekOpen, array $activeNames): string
    {
        if (! $forexWeekOpen || $activeNames === []) {
            return __('trading.market_sessions.none_active');
        }

        return __('trading.market_sessions.active_prefix').' '.implode(', ', $activeNames);
    }

    private function formatPktHours(int $openPkt, int $closePkt): string
    {
        return sprintf(
            '%02d:%02d–%02d:%02d %s',
            intdiv($openPkt, 60),
            $openPkt % 60,
            intdiv($closePkt, 60),
            $closePkt % 60,
            __('trading.market_sessions.pkt_label'),
        );
    }
}
