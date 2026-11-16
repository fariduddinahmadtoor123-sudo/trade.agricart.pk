<?php

namespace Modules\Trading\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;

class Mt5SessionSync
{
    public function __construct(private PropBridgeSnapshot $snapshots) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $accountPayload = is_array($payload['account'] ?? null) ? $payload['account'] : [];
            $this->snapshots->capture($accountPayload);
            $account = $this->account($accountPayload);

            foreach ($this->rows($payload, 'ticks') as $tick) {
                $this->rememberQuote($tick, $accountPayload);
            }

            $live = $this->rememberLive($accountPayload);

            if ($account === null && $live === null) {
                return;
            }

            foreach ($this->rows($payload, 'positions') as $position) {
                $this->applyPosition($account, $live, $position);
            }

            foreach ($this->rows($payload, 'deals') as $deal) {
                $this->applyDeal($account, $live, $deal);
            }
        });
    }

    /**
     * @param  array{status: string, ticket?: int, price?: float|int|string, message?: string}  $payload
     */
    public function applyExecution(TerminalOrder $order, array $payload): TerminalOrder
    {
        if ($payload['status'] === 'rejected') {
            return $this->markRejected($order, (string) ($payload['message'] ?? 'MT5 rejected the order.'));
        }

        return $this->markFilled($order, (int) $payload['ticket'], (float) $payload['price']);
    }

    public function markFilled(TerminalOrder $order, int $ticket, float $price): TerminalOrder
    {
        if ($order->status === TerminalOrderStatus::Closed) {
            return $order;
        }

        if ($order->status === TerminalOrderStatus::Open && (int) $order->mt5_ticket === $ticket) {
            return $order;
        }

        $existing = TerminalOrder::query()
            ->where('mt5_ticket', $ticket)
            ->whereKeyNot($order->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $order->fill([
            'status' => TerminalOrderStatus::Open,
            'mt5_ticket' => $ticket,
            'entry_price' => $price,
            'bridge_message' => null,
            'opened_at' => $order->opened_at ?? now(),
        ])->save();

        return $order->refresh();
    }

    public function markRejected(TerminalOrder $order, string $message): TerminalOrder
    {
        if (in_array($order->status, [TerminalOrderStatus::Open, TerminalOrderStatus::Closed], true)) {
            return $order;
        }

        $order->fill([
            'status' => TerminalOrderStatus::Rejected,
            'bridge_message' => mb_substr($message, 0, 500),
        ])->save();

        return $order->refresh();
    }

    public function markClosed(TerminalOrder $order, float $price, float $profit, ?CarbonInterface $closedAt = null): TerminalOrder
    {
        if ($order->status === TerminalOrderStatus::Closed) {
            return $order;
        }

        $order->fill([
            'status' => TerminalOrderStatus::Closed,
            'exit_price' => $price,
            'realized_pnl' => round($profit, 2),
            'floating_pnl' => null,
            'bridge_message' => null,
            'closed_at' => $closedAt ?? now(),
        ])->save();

        return $order->refresh();
    }

    /**
     * Keep one bridge quote per symbol. A new row every second makes the
     * terminal slower and is not needed for the latest bid and ask.
     *
     * @param  array<string, mixed>  $tick
     * @param  array<string, mixed>  $accountPayload
     */
    private function rememberQuote(array $tick, array $accountPayload): void
    {
        $symbol = strtoupper(trim((string) ($tick['symbol'] ?? '')));

        if ($symbol === '') {
            return;
        }

        $values = [
            'symbol' => $symbol,
            'bid' => is_numeric($tick['bid'] ?? null) ? $tick['bid'] : null,
            'ask' => is_numeric($tick['ask'] ?? null) ? $tick['ask'] : null,
            'source' => 'mt5-bridge',
            'payload' => ['account_number' => $accountPayload['account_number'] ?? null],
            'feed_at' => now(),
        ];

        $existing = TradingLog::query()
            ->where('source', 'mt5-bridge')
            ->whereRaw('upper(symbol) = ?', [$symbol])
            ->latest('id')
            ->first();

        if ($existing === null) {
            TradingLog::query()->create($values);

            return;
        }

        $existing->fill($values)->save();

        TradingLog::query()
            ->where('source', 'mt5-bridge')
            ->whereRaw('upper(symbol) = ?', [$symbol])
            ->whereKeyNot($existing->id)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function account(array $payload): ?PropAccount
    {
        $accountNumber = $payload['account_number'] ?? null;

        if (! is_string($accountNumber) || $accountNumber === '') {
            return null;
        }

        $accounts = PropAccount::query()->where('account_number', $accountNumber);
        $firmName = $payload['firm_name'] ?? null;

        if (is_string($firmName) && $firmName !== '') {
            $accounts->where('firm_name', $firmName);
        }

        return $accounts->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function rows(array $payload, string $key): array
    {
        $rows = $payload[$key] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $position
     */
    private function applyPosition(?PropAccount $account, ?Mt5Account $live, array $position): void
    {
        $side = TerminalOrderSide::tryFrom((string) ($position['side'] ?? ''));

        if ($side === null || ! is_numeric($position['ticket'] ?? null) || ! is_numeric($position['price_open'] ?? null)) {
            return;
        }

        $ticket = (int) $position['ticket'];
        $order = $this->findOrder($account, $live, $ticket, $position['client_order_id'] ?? null);

        if ($order === null) {
            TerminalOrder::query()->create([
                'prop_account_id' => $account?->id,
                'mt5_account_id' => $account === null ? $live?->id : null,
                'symbol' => strtoupper((string) $position['symbol']),
                'side' => $side,
                'lots' => $position['lots'],
                'stop_loss' => $this->level($position['sl'] ?? null),
                'take_profit' => $this->level($position['tp'] ?? null),
                'entry_price' => $position['price_open'],
                'status' => TerminalOrderStatus::Open,
                'mt5_ticket' => $ticket,
                'floating_pnl' => is_numeric($position['profit'] ?? null) ? $position['profit'] : 0,
                'opened_at' => $this->moment($position['opened_at'] ?? null) ?? now(),
            ]);

            return;
        }

        if ($order->status === TerminalOrderStatus::Closed) {
            return;
        }

        $order->fill([
            'status' => TerminalOrderStatus::Open,
            'mt5_ticket' => $ticket,
            'symbol' => strtoupper((string) $position['symbol']),
            'side' => $side,
            'lots' => $position['lots'],
            'stop_loss' => $this->level($position['sl'] ?? null),
            'take_profit' => $this->level($position['tp'] ?? null),
            'entry_price' => $order->entry_price ?: $position['price_open'],
            'floating_pnl' => is_numeric($position['profit'] ?? null) ? $position['profit'] : $order->floating_pnl,
            'bridge_message' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $deal
     */
    private function applyDeal(?PropAccount $account, ?Mt5Account $live, array $deal): void
    {
        if (! in_array($deal['entry'] ?? '', ['out', 'out_by'], true) || ! is_numeric($deal['position_ticket'] ?? null)) {
            return;
        }

        $ticket = (int) $deal['position_ticket'];
        $order = $this->findOrder($account, $live, $ticket, null);

        $closedAt = $this->moment($deal['time'] ?? null) ?? now();
        $price = (float) $deal['price'];
        $profit = (float) $deal['profit'];

        if ($order === null) {
            $closingSide = TerminalOrderSide::tryFrom((string) ($deal['side'] ?? ''));
            $side = match ($closingSide) {
                TerminalOrderSide::Sell => TerminalOrderSide::Buy,
                TerminalOrderSide::Buy => TerminalOrderSide::Sell,
                default => TerminalOrderSide::Buy,
            };

            TerminalOrder::query()->create([
                'prop_account_id' => $account?->id,
                'mt5_account_id' => $account === null ? $live?->id : null,
                'symbol' => strtoupper((string) $deal['symbol']),
                'side' => $side,
                'lots' => $deal['lots'],
                'entry_price' => $price,
                'exit_price' => $price,
                'status' => TerminalOrderStatus::Closed,
                'mt5_ticket' => $ticket,
                'realized_pnl' => round($profit, 2),
                'opened_at' => $closedAt,
                'closed_at' => $closedAt,
            ]);

            return;
        }

        $this->markClosed($order, $price, $profit, $closedAt);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberLive(array $payload): ?Mt5Account
    {
        $number = $payload['account_number'] ?? null;

        if (! is_string($number) && ! is_numeric($number)) {
            return null;
        }

        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $live = Mt5Account::query()->where('login', $number)->first();

        if ($live === null) {
            return null;
        }

        $live->fill([
            'balance' => is_numeric($payload['balance'] ?? null) ? $payload['balance'] : $live->balance,
            'equity' => is_numeric($payload['equity'] ?? null) ? $payload['equity'] : $live->equity,
            'today_pnl' => is_numeric($payload['today_pnl'] ?? null) ? $payload['today_pnl'] : $live->today_pnl,
            'floating_pnl' => is_numeric($payload['floating_pnl'] ?? null) ? $payload['floating_pnl'] : $live->floating_pnl,
            'snapshot_at' => now(),
        ])->save();

        return $live->refresh();
    }

    private function findOrder(?PropAccount $account, ?Mt5Account $live, int $ticket, mixed $clientOrderId): ?TerminalOrder
    {
        if ($account === null && $live === null) {
            return null;
        }

        return TerminalOrder::query()
            ->where(function ($query) use ($account, $live): void {
                if ($account !== null) {
                    $query->orWhere('prop_account_id', $account->id);
                }

                if ($live !== null) {
                    $query->orWhere('mt5_account_id', $live->id);
                }
            })
            ->where(function ($query) use ($ticket, $clientOrderId): void {
                $query->where('mt5_ticket', $ticket);

                if (is_numeric($clientOrderId)) {
                    $query->orWhere('id', (int) $clientOrderId);
                }
            })
            ->orderByRaw('case when mt5_ticket = ? then 0 else 1 end', [$ticket])
            ->first();
    }

    private function level(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value == 0.0) {
            return null;
        }

        return (float) $value;
    }

    private function moment(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
