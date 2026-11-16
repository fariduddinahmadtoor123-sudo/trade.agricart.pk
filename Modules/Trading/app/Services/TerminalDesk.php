<?php

namespace Modules\Trading\Services;

use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Models\TerminalOrder;
use Modules\Trading\Models\TradingLog;

class TerminalDesk
{
    public function __construct(
        private MarketWatch $watch,
        private TerminalPnl $pnl,
    ) {}

    /**
     * @return array{
     *     session: bool,
     *     summary: array{balance: string, equity: string, today: string, today_tone: string}|null,
     *     quotes: list<array{symbol: string, bid: string, ask: string}>,
     *     open: list<array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, stop: string, take: string, pnl: string, pnl_tone: string}>,
     *     history: list<array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, exit: string, pnl: string, pnl_tone: string, time: string}>
     * }
     */
    public function present(?PropAccount $account): array
    {
        $quotes = $this->watch->quotes();
        $bySymbol = $quotes->keyBy(fn (TradingLog $quote): string => strtoupper($quote->symbol));

        return [
            'session' => $account !== null,
            'summary' => $this->summary($account),
            'quotes' => $quotes->map(fn (TradingLog $quote): array => [
                'symbol' => strtoupper($quote->symbol),
                'bid' => $this->price($quote->bid),
                'ask' => $this->price($quote->ask),
            ])->all(),
            'open' => $account === null ? [] : $account->orders()
                ->where('status', TerminalOrderStatus::Open)
                ->orderByDesc('id')
                ->get()
                ->map(fn (TerminalOrder $order): array => $this->openRow($order, $bySymbol->get($order->symbol)))
                ->all(),
            'history' => $account === null ? [] : $account->orders()
                ->where('status', TerminalOrderStatus::Closed)
                ->orderByDesc('closed_at')
                ->limit(40)
                ->get()
                ->map(fn (TerminalOrder $order): array => $this->historyRow($order))
                ->all(),
        ];
    }

    /**
     * @return array{
     *     session: bool,
     *     summary: array{balance: string, equity: string, today: string, today_tone: string}|null,
     *     quotes: list<array{symbol: string, bid: string, ask: string}>,
     *     open: list<array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, stop: string, take: string, pnl: string, pnl_tone: string}>,
     *     history: list<array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, exit: string, pnl: string, pnl_tone: string, time: string}>
     * }
     */
    public function presentLive(Mt5Account $account): array
    {
        $view = $this->present(null);
        $quotes = $this->watch->quotes();
        $bySymbol = $quotes->keyBy(fn (TradingLog $quote): string => strtoupper($quote->symbol));
        $today = is_numeric($account->today_pnl) ? (float) $account->today_pnl : null;

        $view['session'] = true;
        $view['summary'] = $account->snapshot_at === null ? null : [
            'balance' => $this->money($account->balance),
            'equity' => $this->money($account->equity),
            'today' => $today === null ? '—' : $this->signed($today),
            'today_tone' => $today === null || $today === 0.0 ? 'flat' : ($today > 0 ? 'up' : 'down'),
        ];
        $view['open'] = $account->orders()
            ->where('status', TerminalOrderStatus::Open)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TerminalOrder $order): array => $this->openRow($order, $bySymbol->get($order->symbol)))
            ->all();
        $view['history'] = $account->orders()
            ->where('status', TerminalOrderStatus::Closed)
            ->orderByDesc('closed_at')
            ->limit(40)
            ->get()
            ->map(fn (TerminalOrder $order): array => $this->historyRow($order))
            ->all();

        return $view;
    }

    /**
     * @return array{balance: string, equity: string, today: string, today_tone: string}|null
     */
    private function summary(?PropAccount $account): ?array
    {
        if ($account === null || $account->snapshot_at === null) {
            return null;
        }

        $today = is_numeric($account->today_pnl) ? (float) $account->today_pnl : 0.0;

        return [
            'balance' => $this->money($account->current_balance),
            'equity' => $this->money($account->equity),
            'today' => $this->signed($today),
            'today_tone' => $today > 0 ? 'up' : ($today < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * @return array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, stop: string, take: string, pnl: string, pnl_tone: string}
     */
    private function openRow(TerminalOrder $order, ?TradingLog $quote): array
    {
        $mark = $order->side === TerminalOrderSide::Buy ? $quote?->bid : $quote?->ask;
        $pnl = is_numeric($order->floating_pnl)
            ? (float) $order->floating_pnl
            : (is_numeric($mark)
                ? $this->pnl->amount($order->symbol, $order->side, (float) $order->entry_price, (float) $mark, (float) $order->lots)
                : null);

        return [
            'id' => $order->id,
            'ticket' => '#'.($order->mt5_ticket ?? $order->id),
            'symbol' => $order->symbol,
            'side' => $order->side->getLabel(),
            'lots' => number_format((float) $order->lots, 2, '.', ''),
            'entry' => $this->price($order->entry_price),
            'stop' => $this->price($order->stop_loss),
            'take' => $this->price($order->take_profit),
            'pnl' => $pnl === null ? '—' : $this->signed($pnl),
            'pnl_tone' => $pnl === null || $pnl === 0.0 ? 'flat' : ($pnl > 0 ? 'up' : 'down'),
        ];
    }

    /**
     * @return array{id: int, ticket: string, symbol: string, side: string, lots: string, entry: string, exit: string, pnl: string, pnl_tone: string, time: string}
     */
    private function historyRow(TerminalOrder $order): array
    {
        $pnl = is_numeric($order->realized_pnl) ? (float) $order->realized_pnl : 0.0;

        return [
            'id' => $order->id,
            'ticket' => '#'.($order->mt5_ticket ?? $order->id),
            'symbol' => $order->symbol,
            'side' => $order->side->getLabel(),
            'lots' => number_format((float) $order->lots, 2, '.', ''),
            'entry' => $this->price($order->entry_price),
            'exit' => $this->price($order->exit_price),
            'pnl' => $this->signed($pnl),
            'pnl_tone' => $pnl > 0 ? 'up' : ($pnl < 0 ? 'down' : 'flat'),
            'time' => $order->closed_at?->timezone(config('app.timezone'))->format('d M H:i') ?? '',
        ];
    }

    private function price(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $number = (float) $value;

        return number_format($number, abs($number) >= 50 ? 2 : 5, '.', '');
    }

    private function money(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 2, '.', ',');
    }

    private function signed(float $amount): string
    {
        $prefix = $amount > 0 ? '+' : '';

        return $prefix.number_format($amount, 2, '.', ',');
    }
}
