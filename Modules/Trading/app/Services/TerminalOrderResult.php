<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Models\TerminalOrder;

readonly class TerminalOrderResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public bool $accepted,
        public ?TerminalOrder $order,
        public array $messages,
        public ?PropRiskVerdict $verdict = null,
    ) {}
}
