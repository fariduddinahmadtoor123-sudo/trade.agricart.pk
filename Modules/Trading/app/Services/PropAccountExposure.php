<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\DailyDrawdownType;
use Modules\Trading\Enums\MaxDrawdownBasis;
use Modules\Trading\Models\PropAccount;

class PropAccountExposure
{
    /**
     * @return array{
     *     has_snapshot: bool,
     *     risk_base: float,
     *     today_pnl: float|null,
     *     daily_used: float,
     *     daily_limit: float,
     *     total_used: float,
     *     total_limit: float,
     *     profit_used: float,
     *     profit_limit: float
     * }
     */
    public function measure(PropAccount $account): array
    {
        $rule = $account->rule;
        $initial = $this->amount($account->initial_balance);
        $equity = $this->nullableAmount($account->equity);
        $current = $this->nullableAmount($account->current_balance);
        $dayStart = $this->nullableAmount($account->day_start_balance);
        $todayPnl = $this->nullableAmount($account->today_pnl);

        $dailyBase = $rule?->daily_drawdown_type === DailyDrawdownType::Balance
            ? ($current ?? $dayStart ?? $initial)
            : ($dayStart ?? $initial);

        $dailyLoss = 0.0;

        if ($todayPnl !== null) {
            $dailyLoss = max(0, -$todayPnl);
        } elseif ($equity !== null) {
            $dailyLoss = max(0, $dailyBase - $equity);
        }

        $drawdownReference = $rule?->max_drawdown_basis === MaxDrawdownBasis::Balanced
            ? ($current ?? $initial)
            : $initial;

        return [
            'has_snapshot' => $account->snapshot_at !== null,
            'risk_base' => $current ?? $initial,
            'today_pnl' => $todayPnl,
            'daily_used' => $dailyLoss,
            'daily_limit' => $dailyBase * $this->amount($rule?->daily_drawdown_percent) / 100,
            'total_used' => ($equity !== null && $drawdownReference > 0) ? max(0, $drawdownReference - $equity) : 0.0,
            'total_limit' => $drawdownReference * $this->amount($rule?->max_total_drawdown_percent) / 100,
            'profit_used' => ($equity !== null && $initial > 0) ? max(0, $equity - $initial) : 0.0,
            'profit_limit' => $initial * $account->activeProfitTarget() / 100,
        ];
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableAmount(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
