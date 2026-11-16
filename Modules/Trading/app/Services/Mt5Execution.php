<?php

namespace Modules\Trading\Services;

readonly class Mt5Execution
{
    private function __construct(
        public string $state,
        public ?int $ticket = null,
        public ?float $price = null,
        public ?float $profit = null,
        public string $message = '',
        public ?float $stopLoss = null,
        public ?float $takeProfit = null,
    ) {}

    public static function filled(int $ticket, float $price, ?float $profit = null, ?float $stopLoss = null, ?float $takeProfit = null): self
    {
        return new self('filled', $ticket, $price, $profit, stopLoss: $stopLoss, takeProfit: $takeProfit);
    }

    public static function closed(float $price, float $profit): self
    {
        return new self('filled', null, $price, $profit);
    }

    public static function rejected(string $message): self
    {
        return new self('rejected', message: $message);
    }

    public static function unreachable(string $message): self
    {
        return new self('unreachable', message: $message);
    }

    public function isFilled(): bool
    {
        return $this->state === 'filled';
    }

    public function isRejected(): bool
    {
        return $this->state === 'rejected';
    }
}
