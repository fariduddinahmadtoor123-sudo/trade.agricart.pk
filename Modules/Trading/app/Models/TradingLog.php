<?php

namespace Modules\Trading\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Trading\Database\Factories\TradingLogFactory;

#[UseFactory(TradingLogFactory::class)]
#[Fillable([
    'symbol',
    'bid',
    'ask',
    'last',
    'volume',
    'source',
    'payload',
    'feed_at',
])]
class TradingLog extends Model
{
    /** @use HasFactory<TradingLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bid' => 'decimal:8',
            'ask' => 'decimal:8',
            'last' => 'decimal:8',
            'volume' => 'decimal:8',
            'payload' => 'array',
            'feed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<TradingLog>  $query
     * @return Builder<TradingLog>
     */
    #[Scope]
    protected function latestFeed(Builder $query): Builder
    {
        return $query->orderByDesc('feed_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<TradingLog>  $query
     * @return Builder<TradingLog>
     */
    #[Scope]
    protected function forSymbol(Builder $query, string $symbol): Builder
    {
        return $query->where('symbol', $symbol);
    }
}
