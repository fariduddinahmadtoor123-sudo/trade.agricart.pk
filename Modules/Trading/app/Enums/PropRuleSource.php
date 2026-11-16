<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropRuleSource: string implements HasLabel
{
    case Manual = 'manual';
    case Ai = 'ai';

    public function getLabel(): string
    {
        return __('trading.prop.sources.'.$this->value);
    }
}
