<?php

namespace Modules\Trading\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SmartAlertFeed
{
    public function __construct(
        private HighImpactUsdEventSchedule $schedule,
    ) {}

    /**
     * Build recent + upcoming smart-alert activity from preferences and the USD schedule.
     *
     * @param  array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }  $preferences
     * @return list<array{
     *     id: string,
     *     event_key: string,
     *     title: string,
     *     description: string,
     *     currency: string,
     *     impact: int,
     *     impact_label: string,
     *     status: string,
     *     status_label: string,
     *     kind: string,
     *     kind_label: string,
     *     at: CarbonImmutable,
     *     iso: string,
     *     when: string,
     *     event_when: string
     * }>
     */
    public function activity(array $preferences, ?CarbonImmutable $now = null, int $limit = 12): array
    {
        if (! $preferences['high_impact_news']) {
            return [];
        }

        $now = ($now ?? CarbonImmutable::now('America/New_York'))->timezone('America/New_York');

        $events = $this->schedule->upcoming(
            limit: 8,
            now: $now->subHours(6),
            filters: [
                'currencies' => $preferences['currencies'],
                'impacts' => [3],
                'range' => 'week',
            ],
            ignoreRangeWindow: true,
        );

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();

        foreach ($events as $event) {
            /** @var CarbonImmutable $eventAt */
            $eventAt = $event['at'];

            if ($preferences['warn_30']) {
                $rows->push($this->row(
                    event: $event,
                    kind: 'warn_30',
                    triggerAt: $eventAt->subMinutes(30),
                    now: $now,
                ));
            }

            if ($preferences['warn_15']) {
                $rows->push($this->row(
                    event: $event,
                    kind: 'warn_15',
                    triggerAt: $eventAt->subMinutes(15),
                    now: $now,
                ));
            }

            $rows->push($this->row(
                event: $event,
                kind: 'release',
                triggerAt: $eventAt,
                now: $now,
            ));
        }

        return $rows
            ->filter(function (array $row) use ($now): bool {
                if ($row['status'] !== 'passed') {
                    return true;
                }

                return $row['kind'] === 'release'
                    && $row['at']->greaterThanOrEqualTo($now->subHours(6));
            })
            ->sortBy(function (array $row): string {
                $priority = match ($row['status']) {
                    'triggered' => 0,
                    'upcoming' => 1,
                    'scheduled' => 2,
                    default => 3,
                };

                return sprintf('%d-%d', $priority, $row['at']->timestamp);
            })
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @param  array{key: string, name: string, currency: string, impact: int, at: CarbonImmutable, when: string}  $event
     * @return array{
     *     id: string,
     *     event_key: string,
     *     title: string,
     *     description: string,
     *     currency: string,
     *     impact: int,
     *     impact_label: string,
     *     status: string,
     *     status_label: string,
     *     kind: string,
     *     kind_label: string,
     *     at: CarbonImmutable,
     *     iso: string,
     *     when: string,
     *     event_when: string
     * }
     */
    private function row(array $event, string $kind, CarbonImmutable $triggerAt, CarbonImmutable $now): array
    {
        $status = $this->statusFor($triggerAt, $event['at'], $now);

        return [
            'id' => $event['key'].'|'.$kind.'|'.$triggerAt->timestamp,
            'event_key' => $event['key'],
            'title' => $event['name'],
            'description' => $this->description($kind, $event['name'], $event['when']),
            'currency' => $event['currency'],
            'impact' => $event['impact'],
            'impact_label' => __('trading.calendar.impact.high'),
            'status' => $status,
            'status_label' => __('trading.smart_alerts.status.'.$status),
            'kind' => $kind,
            'kind_label' => __('trading.smart_alerts.kinds.'.$kind),
            'at' => $triggerAt,
            'iso' => $triggerAt->toIso8601String(),
            'when' => $triggerAt->format('D, M j · g:i A T'),
            'event_when' => $event['when'],
        ];
    }

    private function statusFor(CarbonImmutable $triggerAt, CarbonImmutable $eventAt, CarbonImmutable $now): string
    {
        if ($eventAt->lessThanOrEqualTo($now)) {
            return 'passed';
        }

        if ($triggerAt->lessThanOrEqualTo($now)) {
            return 'triggered';
        }

        if ($triggerAt->lessThanOrEqualTo($now->addHours(24))) {
            return 'upcoming';
        }

        return 'scheduled';
    }

    private function description(string $kind, string $eventName, string $eventWhen): string
    {
        return match ($kind) {
            'warn_30' => __('trading.smart_alerts.descriptions.warn_30', [
                'event' => $eventName,
                'when' => $eventWhen,
            ]),
            'warn_15' => __('trading.smart_alerts.descriptions.warn_15', [
                'event' => $eventName,
                'when' => $eventWhen,
            ]),
            default => __('trading.smart_alerts.descriptions.release', [
                'event' => $eventName,
                'when' => $eventWhen,
            ]),
        };
    }
}
