<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropPlatform: string implements HasLabel
{
    case Mt5 = 'mt5';
    case CTrader = 'ctrader';

    public function getLabel(): string
    {
        return __('trading.prop.platforms.'.$this->value);
    }
}
