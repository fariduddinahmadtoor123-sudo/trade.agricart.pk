<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropPayoutStyle: string implements HasLabel
{
    case ProfitSplit = 'profit_split';
    case FeeUpfront = 'fee_upfront';

    public function getLabel(): string
    {
        return __('trading.prop.payouts.'.$this->value);
    }
}
