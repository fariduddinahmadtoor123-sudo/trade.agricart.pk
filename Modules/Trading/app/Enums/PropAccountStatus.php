<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropAccountStatus: string implements HasLabel
{
    case Challenge = 'challenge';
    case Funded = 'funded';
    case Breached = 'breached';
    case Paused = 'paused';

    public function getLabel(): string
    {
        return __('trading.prop.statuses.'.$this->value);
    }
}
