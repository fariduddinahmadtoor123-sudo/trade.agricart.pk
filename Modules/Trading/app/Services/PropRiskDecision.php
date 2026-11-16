<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\PropRiskVerdict;

readonly class PropRiskDecision
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public PropRiskVerdict $verdict,
        public array $messages,
    ) {}
}
