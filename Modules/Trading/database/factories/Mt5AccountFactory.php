<?php

namespace Modules\Trading\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trading\Models\Mt5Account;

/**
 * @extends Factory<Mt5Account>
 */
class Mt5AccountFactory extends Factory
{
    protected $model = Mt5Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'login' => fake()->unique()->numerify('########'),
            'server' => 'MetaQuotes-Demo',
            'company' => 'MetaQuotes',
            'holder_name' => fake()->name(),
            'balance' => 10000,
            'equity' => 10000,
            'snapshot_at' => now(),
            'connected_at' => now(),
        ];
    }
}
