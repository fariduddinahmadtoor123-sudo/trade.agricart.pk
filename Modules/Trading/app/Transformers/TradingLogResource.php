<?php

namespace Modules\Trading\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Trading\Models\TradingLog;

/**
 * @mixin TradingLog
 */
class TradingLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'volume' => $this->volume,
            'source' => $this->source,
            'payload' => $this->payload,
            'feed_at' => $this->feed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
