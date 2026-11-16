<?php

namespace Modules\Trading\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Trading\Http\Requests\StoreMarketDataRequest;
use Modules\Trading\Models\TradingLog;
use Modules\Trading\Services\PropBridgeSnapshot;
use Modules\Trading\Transformers\TradingLogResource;

class MarketDataController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $logs = TradingLog::query()
            ->latestFeed()
            ->limit(100)
            ->get();

        return TradingLogResource::collection($logs);
    }

    public function store(StoreMarketDataRequest $request, PropBridgeSnapshot $snapshots): JsonResponse
    {
        $logs = collect($request->ticks())->map(function (array $tick) use ($snapshots): TradingLog {
            $payload = $tick['payload'] ?? null;
            $snapshots->capture(is_array($payload) ? $payload : null);

            return TradingLog::query()->create([
                'symbol' => $tick['symbol'],
                'bid' => $tick['bid'] ?? null,
                'ask' => $tick['ask'] ?? null,
                'last' => $tick['last'] ?? null,
                'volume' => $tick['volume'] ?? null,
                'source' => $tick['source'] ?? 'python-bridge',
                'payload' => $payload,
                'feed_at' => $tick['feed_at'] ?? now(),
            ]);
        });

        return TradingLogResource::collection($logs)
            ->response()
            ->setStatusCode(201);
    }
}
