<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropRiskCategory: string implements HasLabel
{
    case Low = 'low';
    case Moderate = 'moderate';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): string
    {
        return __('trading.prop.risks.'.$this->value);
    }

    public function leverage(): string
    {
        return match ($this) {
            self::Low => '1:200',
            self::Moderate => '1:100',
            self::Medium => '1:30',
            self::High => '1:20/1:30',
        };
    }

    public function maxRiskPercent(): float
    {
        return match ($this) {
            self::Low => 1.0,
            self::Moderate => 2.0,
            self::Medium => 2.5,
            self::High => 3.0,
        };
    }

    public function stopLossMode(): PropStopLossMode
    {
        return match ($this) {
            self::Low => PropStopLossMode::RequiredOnOpenAndClose,
            self::Moderate => PropStopLossMode::RequiredOnClose,
            self::Medium, self::High => PropStopLossMode::Optional,
        };
    }

    public function payout(): PropPayoutStyle
    {
        return match ($this) {
            self::Low, self::Moderate => PropPayoutStyle::ProfitSplit,
            self::Medium, self::High => PropPayoutStyle::FeeUpfront,
        };
    }
}
