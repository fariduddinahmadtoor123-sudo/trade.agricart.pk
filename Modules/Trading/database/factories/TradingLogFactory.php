<?php

namespace Modules\Trading\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trading\Models\TradingLog;

/**
 * @extends Factory<TradingLog>
 */
class TradingLogFactory extends Factory
{
    protected $model = TradingLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bid = fake()->randomFloat(5, 100, 5000);

        return [
            'symbol' => fake()->randomElement(['XAUUSD', 'XAGUSD', 'EURUSD', 'GBPUSD', 'USDJPY']),
            'bid' => $bid,
            'ask' => $bid + fake()->randomFloat(5, 0.01, 0.5),
            'last' => $bid + fake()->randomFloat(5, -0.1, 0.1),
            'volume' => fake()->randomFloat(4, 0.01, 100),
            'source' => 'python-bridge',
            'payload' => [
                'bridge' => 'python',
                'tick_id' => fake()->uuid(),
            ],
            'feed_at' => now(),
        ];
    }
}
