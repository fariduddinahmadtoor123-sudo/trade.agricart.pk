<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\TerminalOrderSide;

class TerminalPnl
{
    public function contractSize(string $symbol): int
    {
        $symbol = strtoupper($symbol);

        if (str_starts_with($symbol, 'XAU')) {
            return 100;
        }

        if (str_starts_with($symbol, 'XAG')) {
            return 5000;
        }

        return 100_000;
    }

    public function amount(string $symbol, TerminalOrderSide $side, float $entry, float $mark, float $lots): float
    {
        $direction = $side === TerminalOrderSide::Buy ? 1 : -1;

        return round(($mark - $entry) * $direction * $lots * $this->contractSize($symbol), 2);
    }
}
