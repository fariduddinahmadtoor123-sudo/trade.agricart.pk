<?php

namespace Modules\Trading\Enums;

use Filament\Support\Contracts\HasLabel;

enum PropStopLossMode: string implements HasLabel
{
    case RequiredOnOpenAndClose = 'required_both';
    case RequiredOnOpen = 'required_open';
    case RequiredOnClose = 'required_close';
    case Optional = 'optional';

    public function getLabel(): string
    {
        return __('trading.prop.stop_loss_modes.'.$this->value);
    }

    public function requiresStop(): bool
    {
        return $this !== self::Optional;
    }
}
