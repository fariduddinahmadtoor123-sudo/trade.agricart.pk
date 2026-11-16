<?php

namespace Modules\Trading\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Trading\Models\TradingLog;

class MarketWatch
{
    /**
     * @return Collection<int, TradingLog>
     */
    public function quotes(): Collection
    {
        $latestIds = TradingLog::query()
            ->selectRaw('max(trading_logs.id) as id')
            ->joinSub(
                TradingLog::query()
                    ->selectRaw('upper(symbol) as symbol_key, max(feed_at) as feed_at')
                    ->groupByRaw('upper(symbol)'),
                'latest_feed',
                function ($join): void {
                    $join->whereRaw('upper(trading_logs.symbol) = latest_feed.symbol_key')
                        ->whereColumn('trading_logs.feed_at', 'latest_feed.feed_at');
                },
            )
            ->groupByRaw('upper(trading_logs.symbol)');

        return TradingLog::query()
            ->select('trading_logs.*')
            ->joinSub($latestIds, 'latest_quotes', 'latest_quotes.id', '=', 'trading_logs.id')
            ->orderBy('trading_logs.symbol')
            ->get();
    }

    public function quote(string $symbol): ?TradingLog
    {
        $symbol = strtoupper(trim($symbol));

        if ($symbol === '') {
            return null;
        }

        return TradingLog::query()
            ->whereRaw('upper(symbol) = ?', [$symbol])
            ->latestFeed()
            ->first();
    }
}
