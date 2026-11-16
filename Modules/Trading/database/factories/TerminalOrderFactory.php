<?php

namespace Modules\Trading\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;

/**
 * @extends Factory<TerminalOrder>
 */
class TerminalOrderFactory extends Factory
{
    protected $model = TerminalOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prop_account_id' => PropAccount::factory(),
            'symbol' => 'EURUSD',
            'side' => TerminalOrderSide::Buy,
            'lots' => 0.10,
            'stop_loss' => 1.09000,
            'take_profit' => 1.11000,
            'entry_price' => 1.10000,
            'exit_price' => null,
            'status' => TerminalOrderStatus::Open,
            'realized_pnl' => null,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }
}
