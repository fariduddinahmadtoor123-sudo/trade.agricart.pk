<?php

namespace Modules\Trading\Services;

use Illuminate\Support\Facades\Cache;

class SmartAlertPreferences
{
    /**
     * @var list<string>
     */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'JPY'];

    private const CACHE_PREFIX = 'trading.smart_alerts.preferences.';

    /**
     * @return array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }
     */
    public function defaults(): array
    {
        return [
            'high_impact_news' => true,
            'warn_15' => true,
            'warn_30' => true,
            'currencies' => ['USD'],
        ];
    }

    /**
     * @return array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }
     */
    public function forUser(int $userId): array
    {
        $stored = Cache::get($this->cacheKey($userId));

        if (! is_array($stored)) {
            return $this->defaults();
        }

        return $this->normalize($stored);
    }

    /**
     * @param  array{
     *     high_impact_news?: bool,
     *     warn_15?: bool,
     *     warn_30?: bool,
     *     currencies?: list<string>
     * }  $preferences
     * @return array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }
     */
    public function update(int $userId, array $preferences): array
    {
        $normalized = $this->normalize([
            ...$this->forUser($userId),
            ...$preferences,
        ]);

        Cache::forever($this->cacheKey($userId), $normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{
     *     high_impact_news: bool,
     *     warn_15: bool,
     *     warn_30: bool,
     *     currencies: list<string>
     * }
     */
    public function normalize(array $preferences): array
    {
        $currencies = collect($preferences['currencies'] ?? [])
            ->filter(fn (mixed $currency): bool => is_string($currency))
            ->map(fn (string $currency): string => strtoupper($currency))
            ->filter(fn (string $currency): bool => in_array($currency, self::CURRENCIES, true))
            ->unique()
            ->values()
            ->all();

        if ($currencies === []) {
            $currencies = ['USD'];
        }

        return [
            'high_impact_news' => (bool) ($preferences['high_impact_news'] ?? true),
            'warn_15' => (bool) ($preferences['warn_15'] ?? true),
            'warn_30' => (bool) ($preferences['warn_30'] ?? true),
            'currencies' => $currencies,
        ];
    }

    private function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX.$userId;
    }
}
