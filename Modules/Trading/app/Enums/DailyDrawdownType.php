<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum DailyDrawdownType: string implements HasLabel
{
    case EndOfDay = 'eod';
    case Balance = 'balance';

    public function getLabel(): string
    {
        return __('trading.prop.drawdown_types.'.$this->value);
    }
}
