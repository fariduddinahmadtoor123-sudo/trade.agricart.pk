<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum MaxDrawdownBasis: string implements HasLabel
{
    case Balanced = 'balanced';
    case Static = 'static';

    public function getLabel(): string
    {
        return __('trading.prop.drawdown_basis.'.$this->value);
    }
}
