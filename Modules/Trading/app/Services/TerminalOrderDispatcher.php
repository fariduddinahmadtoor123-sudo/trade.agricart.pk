<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;

class TerminalOrderDispatcher
{
    public function __construct(
        private MarketWatch $watch,
        private PropRiskGuard $guard,
        private TerminalPnl $pnl,
        private Mt5BridgeClient $bridge,
        private Mt5SessionSync $sync,
    ) {}

    public function place(
        PropAccount $account,
        string $symbol,
        TerminalOrderSide $side,
        string $lots,
        string $stopLoss,
        string $takeProfit,
    ): TerminalOrderResult {
        $prepared = $this->prepare($symbol, $side, $lots, $stopLoss, $takeProfit);

        if ($prepared instanceof TerminalOrderResult) {
            return $prepared;
        }

        $symbol = $prepared['symbol'];
        $lots = $prepared['lots'];
        $stop = $prepared['stop'];
        $take = $prepared['take'];
        $entry = $prepared['entry'];
        $riskPercent = $this->riskPercent($account, $symbol, $entry, $stop, $lots);
        $decision = $this->guard->assess($account, $riskPercent, $stop !== null);

        if ($decision->verdict === PropRiskVerdict::Block) {
            return new TerminalOrderResult(false, null, $decision->messages, $decision->verdict);
        }

        return $this->openOnBridge($account, null, $symbol, $side, (float) $lots, $stop, $take, $entry, $decision->messages, $decision->verdict);
    }

    public function placeLive(
        Mt5Account $account,
        string $symbol,
        TerminalOrderSide $side,
        string $lots,
        string $stopLoss,
        string $takeProfit,
        bool $protectEquity = false,
    ): TerminalOrderResult {
        $prepared = $this->prepare($symbol, $side, $lots, $stopLoss, $takeProfit, true);

        if ($prepared instanceof TerminalOrderResult) {
            return $prepared;
        }

        return $this->openOnBridge(
            null,
            $account,
            $prepared['symbol'],
            $side,
            $prepared['lots'],
            $prepared['stop'],
            $prepared['take'],
            $prepared['entry'],
            [],
            PropRiskVerdict::Allow,
            $protectEquity ? 1.0 : null,
        );
    }

    /**
     * @param  list<string>  $messages
     */
    private function openOnBridge(
        ?PropAccount $propAccount,
        ?Mt5Account $liveAccount,
        string $symbol,
        TerminalOrderSide $side,
        float $lots,
        ?float $stop,
        ?float $take,
        float $entry,
        array $messages,
        PropRiskVerdict $verdict,
        ?float $riskPercent = null,
    ): TerminalOrderResult {
        if (! $this->bridge->isConfigured()) {
            return $this->reject(__('trading.terminal.bridge_not_configured'));
        }

        $order = TerminalOrder::query()->create([
            'prop_account_id' => $propAccount?->id,
            'mt5_account_id' => $liveAccount?->id,
            'symbol' => $symbol,
            'side' => $side,
            'lots' => $lots,
            'stop_loss' => $stop,
            'take_profit' => $take,
            'entry_price' => $entry,
            'status' => TerminalOrderStatus::Sending,
            'opened_at' => now(),
        ]);

        if ($propAccount !== null) {
            $order->setRelation('account', $propAccount);
        }

        if ($liveAccount !== null) {
            $order->setRelation('liveAccount', $liveAccount);
        }

        $execution = $this->bridge->execute($order, $riskPercent);

        if ($execution->isFilled() && $execution->ticket !== null && $execution->price !== null) {
            $order = $this->sync->markFilled($order, $execution->ticket, $execution->price);

            if ($execution->stopLoss !== null || $execution->takeProfit !== null) {
                $order->fill([
                    'stop_loss' => $execution->stopLoss ?? $order->stop_loss,
                    'take_profit' => $execution->takeProfit ?? $order->take_profit,
                ])->save();
            }
            $messages[] = __('trading.terminal.placed', [
                'ticket' => '#'.$order->mt5_ticket,
                'price' => $this->plainPrice((float) $order->entry_price),
            ]);

            return new TerminalOrderResult(true, $order, $messages, $verdict);
        }

        if ($execution->isRejected()) {
            $order = $this->sync->markRejected($order, $execution->message);

            return new TerminalOrderResult(false, $order, [
                __('trading.terminal.bridge_rejected', ['reason' => $execution->message]),
            ], PropRiskVerdict::Block);
        }

        $order->fill([
            'status' => TerminalOrderStatus::Pending,
            'bridge_message' => mb_substr($execution->message, 0, 500),
        ])->save();

        return new TerminalOrderResult(false, $order->refresh(), [
            __('trading.terminal.bridge_queued'),
        ], PropRiskVerdict::Alert);
    }

    public function close(PropAccount $account, int $orderId): TerminalOrderResult
    {
        $order = TerminalOrder::query()
            ->whereKey($orderId)
            ->where('prop_account_id', $account->id)
            ->where('status', TerminalOrderStatus::Open)
            ->first();

        if ($order === null) {
            return $this->reject(__('trading.terminal.missing_order'));
        }

        if (! $this->bridge->isConfigured()) {
            return $this->reject(__('trading.terminal.bridge_not_configured'));
        }

        $order->setRelation('account', $account);
        $execution = $this->bridge->close($order);

        if ($execution->isFilled() && $execution->price !== null) {
            $profit = $execution->profit ?? $this->fallbackProfit($order, $execution->price);
            $order = $this->sync->markClosed($order, $execution->price, $profit);

            return new TerminalOrderResult(true, $order, [
                __('trading.terminal.closed', [
                    'ticket' => '#'.($order->mt5_ticket ?? $order->id),
                    'pnl' => $this->signed((float) $order->realized_pnl),
                ]),
            ], PropRiskVerdict::Allow);
        }

        if ($execution->isRejected()) {
            return new TerminalOrderResult(false, $order, [
                __('trading.terminal.bridge_rejected', ['reason' => $execution->message]),
            ], PropRiskVerdict::Block);
        }

        return new TerminalOrderResult(false, $order, [
            __('trading.terminal.bridge_close_queued'),
        ], PropRiskVerdict::Alert);
    }

    public function closeLive(Mt5Account $account, int $orderId): TerminalOrderResult
    {
        $order = TerminalOrder::query()
            ->whereKey($orderId)
            ->where('mt5_account_id', $account->id)
            ->where('status', TerminalOrderStatus::Open)
            ->first();

        if ($order === null) {
            return $this->reject(__('trading.terminal.missing_order'));
        }

        if (! $this->bridge->isConfigured()) {
            return $this->reject(__('trading.terminal.bridge_not_configured'));
        }

        $order->setRelation('liveAccount', $account);

        return $this->finishClose($order);
    }

    private function finishClose(TerminalOrder $order): TerminalOrderResult
    {
        $execution = $this->bridge->close($order);

        if ($execution->isFilled() && $execution->price !== null) {
            $profit = $execution->profit ?? $this->fallbackProfit($order, $execution->price);
            $order = $this->sync->markClosed($order, $execution->price, $profit);

            return new TerminalOrderResult(true, $order, [
                __('trading.terminal.closed', [
                    'ticket' => '#'.($order->mt5_ticket ?? $order->id),
                    'pnl' => $this->signed((float) $order->realized_pnl),
                ]),
            ], PropRiskVerdict::Allow);
        }

        if ($execution->isRejected()) {
            return new TerminalOrderResult(false, $order, [
                __('trading.terminal.bridge_rejected', ['reason' => $execution->message]),
            ], PropRiskVerdict::Block);
        }

        return new TerminalOrderResult(false, $order, [
            __('trading.terminal.bridge_close_queued'),
        ], PropRiskVerdict::Alert);
    }

    /**
     * @return array{symbol: string, lots: float, stop: ?float, take: ?float, entry: float}|TerminalOrderResult
     */
    private function prepare(
        string $symbol,
        TerminalOrderSide $side,
        string $lots,
        string $stopLoss,
        string $takeProfit,
        bool $live = false,
    ): array|TerminalOrderResult {
        $symbol = strtoupper(trim($symbol));

        if (! preg_match('/^[A-Z0-9._-]{1,32}$/', $symbol)) {
            return $this->reject(__('trading.terminal.invalid_symbol'));
        }

        if (! is_numeric($lots) || (float) $lots <= 0 || (float) $lots > 500) {
            return $this->reject(__('trading.terminal.invalid_lots'));
        }

        $stop = $this->price($stopLoss);
        $take = $this->price($takeProfit);

        if ($stop === false || $take === false) {
            return $this->reject(__('trading.terminal.invalid_price'));
        }

        $entry = $live ? $this->livePrice($symbol, $side) : null;

        if ($entry === null) {
            $quote = $this->watch->quote($symbol);
            $entry = $this->fillPrice($quote, $side);
        }

        if ($entry === null) {
            return $this->reject(__('trading.terminal.no_quote'));
        }

        if ($stop !== null && ! $this->stopIsValid($side, $entry, $stop)) {
            return $this->reject(__('trading.terminal.stop_side'));
        }

        if ($take !== null && ! $this->targetIsValid($side, $entry, $take)) {
            return $this->reject(__('trading.terminal.take_side'));
        }

        return [
            'symbol' => $symbol,
            'lots' => (float) $lots,
            'stop' => $stop,
            'take' => $take,
            'entry' => $entry,
        ];
    }

    private function fallbackProfit(TerminalOrder $order, float $exit): float
    {
        return $this->pnl->amount(
            $order->symbol,
            $order->side,
            (float) $order->entry_price,
            $exit,
            (float) $order->lots,
        );
    }

    private function reject(string $message): TerminalOrderResult
    {
        return new TerminalOrderResult(false, null, [$message]);
    }

    private function price(string $value): float|false|null
    {
        if (trim($value) === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return false;
        }

        return (float) $value;
    }

    private function livePrice(string $symbol, TerminalOrderSide $side): ?float
    {
        foreach ($this->bridge->quotes() as $quote) {
            if (strtoupper((string) ($quote['symbol'] ?? '')) !== $symbol) {
                continue;
            }

            $value = $side === TerminalOrderSide::Buy ? ($quote['ask'] ?? null) : ($quote['bid'] ?? null);

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    private function fillPrice(?TradingLog $quote, TerminalOrderSide $side): ?float
    {
        $value = $side === TerminalOrderSide::Buy ? $quote?->ask : $quote?->bid;

        return is_numeric($value) ? (float) $value : null;
    }

    private function stopIsValid(TerminalOrderSide $side, float $entry, float $stop): bool
    {
        return $side === TerminalOrderSide::Buy ? $stop < $entry : $stop > $entry;
    }

    private function targetIsValid(TerminalOrderSide $side, float $entry, float $target): bool
    {
        return $side === TerminalOrderSide::Buy ? $target > $entry : $target < $entry;
    }

    private function riskPercent(PropAccount $account, string $symbol, float $entry, ?float $stop, float $lots): float
    {
        $balance = is_numeric($account->current_balance)
            ? (float) $account->current_balance
            : (float) $account->initial_balance;

        if ($stop === null || $balance <= 0) {
            return 0.0;
        }

        $riskMoney = abs($entry - $stop) * $lots * $this->pnl->contractSize($symbol);

        return round($riskMoney / $balance * 100, 4);
    }

    private function plainPrice(float $price): string
    {
        $decimals = abs($price) >= 50 ? 2 : 5;

        return number_format($price, $decimals, '.', '');
    }

    private function signed(float $amount): string
    {
        $prefix = $amount > 0 ? '+' : '';

        return $prefix.number_format($amount, 2, '.', ',');
    }
}
