<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\PropAccount;

class PropRulePreset
{
    public function sync(PropAccount $account): void
    {
        $model = $account->model_type;
        $rule = $account->rule()->first();

        if ($model === null || $rule === null) {
            return;
        }

        $preset = $model->rules();
        $category = $account->risk_category;
        $stopLoss = $category->stopLossMode();

        $rule->fill([
            'profit_target_percent' => $preset['phase_one_target'],
            'phase_two_profit_target_percent' => $preset['phase_two_target'],
            'daily_drawdown_percent' => $preset['daily_drawdown'],
            'daily_drawdown_type' => $preset['daily_drawdown_type'],
            'max_total_drawdown_percent' => $preset['max_drawdown'],
            'max_drawdown_basis' => $preset['max_drawdown_basis'],
            'max_loss_per_trade_percent' => $preset['max_loss_per_trade'],
            'minimum_trading_days' => $preset['minimum_trading_days'],
            'leverage' => $category->leverage(),
            'stop_loss_mode' => $stopLoss,
            'stop_loss_required' => $stopLoss->requiresStop(),
            'payout_style' => $category->payout(),
        ]);

        $rule->saveQuietly();
    }
}
