<?php

namespace Modules\Trading\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropFundingModel;
use Modules\Trading\Enums\PropPlatform;
use Modules\Trading\Enums\PropRiskCategory;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\PropAccountRule;
use Modules\Trading\Services\PropRulePreset;

/**
 * @extends Factory<PropAccount>
 */
class PropAccountFactory extends Factory
{
    protected $model = PropAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firm_name' => 'FundedHive',
            'account_number' => fake()->unique()->numerify('FH-####'),
            'platform' => PropPlatform::Mt5,
            'status' => PropAccountStatus::Funded,
            'model_type' => PropFundingModel::TwoStep,
            'phase' => 1,
            'risk_category' => PropRiskCategory::Moderate,
            'initial_balance' => 100000,
        ];
    }

    public function withRule(): static
    {
        return $this->afterCreating(function (PropAccount $account): void {
            PropAccountRule::factory()->for($account, 'account')->create();
            app(PropRulePreset::class)->sync($account);
        });
    }
}
