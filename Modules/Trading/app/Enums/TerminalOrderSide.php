<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum TerminalOrderSide: string implements HasLabel
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function getLabel(): string
    {
        return __('trading.terminal.sides.'.$this->value);
    }
}
