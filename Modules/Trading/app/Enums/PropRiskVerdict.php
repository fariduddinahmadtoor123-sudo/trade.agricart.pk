<?php

namespace Modules\Trading\Enums;

enum PropRiskVerdict: string
{
    case Allow = 'allow';
    case Alert = 'alert';
    case Block = 'block';
}
