<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropFundingModel: string implements HasLabel
{
    case OneStep = 'one_step';
    case TwoStep = 'two_step';

    public function getLabel(): string
    {
        return __('trading.prop.models.'.$this->value);
    }

    /**
     * @return array{
     *     phase_one_target: float,
     *     phase_two_target: float|null,
     *     daily_drawdown: float,
     *     daily_drawdown_type: DailyDrawdownType,
     *     max_drawdown: float,
     *     max_drawdown_basis: MaxDrawdownBasis,
     *     max_loss_per_trade: float,
     *     minimum_trading_days: int
     * }
     */
    public function rules(): array
    {
        $shared = [
            'daily_drawdown' => 5.0,
            'daily_drawdown_type' => DailyDrawdownType::EndOfDay,
            'max_drawdown' => 10.0,
            'max_loss_per_trade' => 3.0,
            'minimum_trading_days' => 3,
        ];

        return match ($this) {
            self::OneStep => [
                ...$shared,
                'phase_one_target' => 10.0,
                'phase_two_target' => null,
                'max_drawdown_basis' => MaxDrawdownBasis::Balanced,
            ],
            self::TwoStep => [
                ...$shared,
                'phase_one_target' => 8.0,
                'phase_two_target' => 6.0,
                'max_drawdown_basis' => MaxDrawdownBasis::Static,
            ],
        };
    }

    public function activeTarget(int $phase): float
    {
        $rules = $this->rules();

        if ($this === self::TwoStep && $phase === 2) {
            return (float) $rules['phase_two_target'];
        }

        return (float) $rules['phase_one_target'];
    }
}
