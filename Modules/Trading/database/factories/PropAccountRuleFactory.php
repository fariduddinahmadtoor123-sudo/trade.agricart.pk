<?php

namespace Modules\Trading\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trading\Enums\DailyDrawdownType;
use Modules\Trading\Enums\PropRuleSource;
use Modules\Trading\Enums\PropStopLossMode;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\PropAccountRule;

/**
 * @extends Factory<PropAccountRule>
 */
class PropAccountRuleFactory extends Factory
{
    protected $model = PropAccountRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prop_account_id' => PropAccount::factory(),
            'source' => PropRuleSource::Manual,
            'profit_target_percent' => 8,
            'daily_drawdown_percent' => 5,
            'daily_drawdown_type' => DailyDrawdownType::EndOfDay,
            'max_total_drawdown_percent' => 10,
            'max_loss_per_trade_percent' => 1,
            'minimum_trading_days' => 5,
            'leverage' => '1:100',
            'stop_loss_mode' => PropStopLossMode::RequiredOnOpen,
            'stop_loss_required' => true,
            'stop_loss_notes' => 'A hard stop is required before the position is opened.',
        ];
    }
}
