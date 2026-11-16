<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\PropAccount;

class PropAccountMeters
{
    public function __construct(private PropAccountExposure $exposure) {}

    /**
     * @return list<array{key: string, label: string, display: string, width: float, meta: string, tone: string, breach: string|null}>
     */
    public function forAccount(?PropAccount $account, float $tradeRisk = 0): array
    {
        $rule = $account?->rule;

        if ($account === null || $rule === null) {
            return [];
        }

        $measured = $this->exposure->measure($account);
        $dailyWidth = $this->width($measured['daily_used'], $measured['daily_limit']);
        $dailyMeta = $this->meta($measured['has_snapshot'], (float) $rule->daily_drawdown_percent)
            .' · '.$rule->daily_drawdown_type->getLabel();
        $lossCap = $account->activeLossCap();

        return [
            $this->meter(
                'profit_target',
                __('trading.prop.terminal.phase_target', ['phase' => $account->activePhase()]),
                $this->percentUsed($measured['profit_used'], $measured['profit_limit']),
                $this->width($measured['profit_used'], $measured['profit_limit']),
                $this->meta($measured['has_snapshot'], $account->activeProfitTarget()),
                false,
            ),
            $this->meter(
                'today_pnl',
                __('trading.prop.terminal.today_pnl'),
                $this->money($measured['today_pnl']),
                $dailyWidth,
                $dailyMeta,
                true,
            ),
            $this->meter(
                'total_drawdown',
                __('trading.prop.terminal.total_drawdown'),
                $this->percentUsed($measured['total_used'], $measured['total_limit']),
                $this->width($measured['total_used'], $measured['total_limit']),
                $this->meta($measured['has_snapshot'], (float) $rule->max_total_drawdown_percent)
                    .' · '.($rule->max_drawdown_basis?->getLabel() ?? ''),
                true,
            ),
            $this->meter(
                'loss_per_trade',
                __('trading.prop.terminal.loss_per_trade'),
                number_format(max(0, $tradeRisk), 2).'%',
                $this->width(max(0, $tradeRisk), $lossCap),
                $this->meta($tradeRisk > 0, $lossCap),
                true,
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, display: string, width: float, meta: string, tone: string, breach: string|null}
     */
    private function meter(string $key, string $label, string $display, float $width, string $meta, bool $warns): array
    {
        $tone = 'ok';

        if ($warns && $width >= 100) {
            $tone = 'danger';
        } elseif ($warns && $width >= 80) {
            $tone = 'alert';
        }

        return [
            'key' => $key,
            'label' => $label,
            'display' => $display,
            'width' => $width,
            'meta' => $meta,
            'tone' => $tone,
            'breach' => $tone === 'danger' ? __('trading.prop.terminal.breached') : null,
        ];
    }

    private function width(float $used, float $limit): float
    {
        if ($limit <= 0) {
            return 0;
        }

        return max(0, min(100, $used / $limit * 100));
    }

    private function percentUsed(float $used, float $limit): string
    {
        return number_format($this->width($used, $limit), 2).'%';
    }

    private function meta(bool $hasSnapshot, float $ceiling): string
    {
        $key = $hasSnapshot ? 'trading.prop.terminal.used_of' : 'trading.prop.terminal.waiting';

        return __($key, ['ceiling' => number_format($ceiling, 2)]);
    }

    private function money(?float $amount): string
    {
        if ($amount === null || abs($amount) < 0.00001) {
            return '0.00';
        }

        $formatted = number_format(abs($amount), 2);

        return $amount > 0 ? '+'.$formatted : '-'.$formatted;
    }
}
