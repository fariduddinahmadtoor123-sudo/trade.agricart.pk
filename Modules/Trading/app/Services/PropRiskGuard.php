<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\PropAccountRule;

class PropRiskGuard
{
    public function __construct(private PropAccountExposure $exposure) {}

    /**
     * @return array{min: float, max: float}
     */
    public function overrideBounds(PropAccountRule $rule): array
    {
        $max = min(1.0, max(0, (float) $rule->max_loss_per_trade_percent));

        return [
            'min' => min(0.5, $max),
            'max' => $max,
        ];
    }

    public function acceptsOverride(PropAccountRule $rule, float $riskPercent): bool
    {
        $bounds = $this->overrideBounds($rule);

        return $riskPercent >= $bounds['min'] - 0.00001
            && $riskPercent <= $bounds['max'] + 0.00001;
    }

    public function assess(PropAccount $account, float $riskPercent, bool $hasStopLoss): PropRiskDecision
    {
        $rule = $account->rule;

        if ($rule === null) {
            return new PropRiskDecision(PropRiskVerdict::Block, [
                __('trading.prop.risk.missing_rules'),
            ]);
        }

        $messages = [];
        $verdict = PropRiskVerdict::Allow;
        $firmMax = $account->activeLossCap();

        if ($riskPercent > $firmMax + 0.00001) {
            $verdict = PropRiskVerdict::Block;
            $messages[] = __('trading.prop.risk.loss_per_trade', [
                'max' => number_format($firmMax, 2),
            ]);
        } elseif ($firmMax > 0 && $riskPercent >= $firmMax * 0.8) {
            $verdict = PropRiskVerdict::Alert;
            $messages[] = __('trading.prop.risk.loss_per_trade_alert', [
                'max' => number_format($firmMax, 2),
            ]);
        }

        if (
            $account->status === PropAccountStatus::Funded
            && $account->risk_category->stopLossMode()->requiresStop()
            && ! $hasStopLoss
        ) {
            $verdict = PropRiskVerdict::Block;
            $messages[] = __('trading.prop.risk.stop_required');
        }

        $measured = $this->exposure->measure($account);

        if ($measured['risk_base'] <= 0) {
            $verdict = PropRiskVerdict::Block;
            $messages[] = __('trading.prop.risk.missing_balance');
        } else {
            $proposed = $measured['risk_base'] * $riskPercent / 100;
            $verdict = $this->worse($verdict, $this->limitVerdict(
                $measured['daily_used'] + $proposed,
                $measured['daily_limit'],
                'daily',
                $messages,
            ));
            $verdict = $this->worse($verdict, $this->limitVerdict(
                $measured['total_used'] + $proposed,
                $measured['total_limit'],
                'total',
                $messages,
            ));
        }

        if ($messages === []) {
            $messages[] = __('trading.prop.risk.clear');
        }

        return new PropRiskDecision($verdict, $messages);
    }

    /**
     * @param  list<string>  $messages
     */
    private function limitVerdict(float $projected, float $limit, string $kind, array &$messages): PropRiskVerdict
    {
        if ($projected <= 0.00001) {
            return PropRiskVerdict::Allow;
        }

        if ($limit <= 0 || $projected > $limit + 0.00001) {
            $messages[] = __('trading.prop.risk.'.$kind.'_blocked');

            return PropRiskVerdict::Block;
        }

        if ($projected >= $limit * 0.8) {
            $messages[] = __('trading.prop.risk.'.$kind.'_alert');

            return PropRiskVerdict::Alert;
        }

        return PropRiskVerdict::Allow;
    }

    private function worse(PropRiskVerdict $current, PropRiskVerdict $next): PropRiskVerdict
    {
        $rank = [
            PropRiskVerdict::Allow->value => 0,
            PropRiskVerdict::Alert->value => 1,
            PropRiskVerdict::Block->value => 2,
        ];

        return $rank[$next->value] > $rank[$current->value] ? $next : $current;
    }
}
