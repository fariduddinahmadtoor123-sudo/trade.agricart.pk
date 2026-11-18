<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Services\Mt5AccountSession;
use Modules\Trading\Services\Mt5BridgeClient;
use Modules\Trading\Services\Mt5DeskConnection;
use Modules\Trading\Services\PropAccountMeters;
use Modules\Trading\Services\PropAccountSession;
use Modules\Trading\Services\PropRiskGuard;
use Modules\Trading\Services\TerminalDesk;
use Modules\Trading\Services\TerminalOrderDispatcher;

class Mt5Terminal extends Page
{
    use HasCleanTradingPage;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?string $slug = 'mt5-terminal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string $translationKey = 'mt5_terminal';

    protected static ?string $navigationLabel = 'MT5 Terminal';

    protected static ?string $title = 'MT5 Terminal';

    protected static ?int $navigationSort = 5;

    protected string $view = 'trading::filament.pages.mt5-terminal';

    public ?string $accountId = null;

    public string $riskPercent = '0.50';

    public bool $stopPlaced = true;

    public ?string $riskVerdict = null;

    /**
     * @var list<string>
     */
    public array $riskMessages = [];

    public string $orderSymbol = '';

    public string $lots = '0.01';

    public string $stopLoss = '';

    public string $takeProfit = '';

    public ?string $orderTone = null;

    /**
     * @var list<string>
     */
    public array $orderMessages = [];

    public string $mt5Login = '';

    public string $mt5Password = '';

    public string $mt5Server = '';

    public bool $rememberMt5 = false;

    public ?string $mt5Status = null;

    public string $category = '';

    /**
     * @var list<string>
     */
    public array $watchlist = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $boardRows = [];

    public string $candleFrame = '15m';

    /**
     * @var list<array<string, mixed>>
     */
    public array $candles = [];

    public string $pendingType = 'buy_limit';

    public string $pendingSymbol = '';

    public string $pendingLots = '0.01';

    public string $pendingPrice = '';

    public string $pendingStop = '';

    public string $pendingTake = '';

    public string $historyDays = '30';

    /**
     * @var list<array<string, mixed>>
     */
    public array $archive = [];

    public string $stopPoints = '100';

    public function mount(PropAccountSession $session, Mt5AccountSession $live): void
    {
        $current = $session->id();

        $this->accountId = $current === null ? null : (string) $current;

        $remembered = $session->riskPercent();

        if ($remembered !== null) {
            $this->riskPercent = number_format($remembered, 2, '.', '');
        }

        $saved = $live->current();
        $this->rememberMt5 = session('trading.terminal.remember_mt5') === true
            || request()->cookie('mt5_remember') === '1';

        if ($saved !== null) {
            $this->mt5Login = $saved->login;
            $this->mt5Server = (string) $saved->server;
            $this->fillRememberedPassword($saved);
            $this->mt5Status = __('trading.terminal.mt5_connected', ['login' => $saved->login]);
        }

        $savedWatch = session('trading.terminal.watchlist', []);
        $this->watchlist = is_array($savedWatch)
            ? array_values(array_filter($savedWatch, is_string(...)))
            : [];

        if (app()->runningUnitTests()) {
            return;
        }

        if ($this->watchlist !== []) {
            app(Mt5BridgeClient::class)->replaceWatchlist($this->watchlist);
        }

        $pulled = app(Mt5DeskConnection::class)->rememberFromTerminal();

        if ($pulled === null) {
            return;
        }

        $this->mt5Login = $pulled->login;
        $this->mt5Server = (string) $pulled->server;
        $this->fillRememberedPassword($pulled);
        $this->mt5Status = __('trading.terminal.mt5_connected', ['login' => $pulled->login]);
    }

    public function updatedRememberMt5(): void
    {
        $this->storeRememberChoice();

        if (! $this->rememberMt5) {
            $this->mt5Password = '';

            return;
        }

        $account = app(Mt5AccountSession::class)->current();

        if ($account !== null) {
            $this->fillRememberedPassword($account);
        }
    }

    public function connectMt5(Mt5DeskConnection $connection): void
    {
        $error = $connection->connect($this->mt5Login, $this->mt5Password, $this->mt5Server);
        $this->storeRememberChoice();

        if (! $this->rememberMt5) {
            $this->mt5Password = '';
        }

        if ($error !== null) {
            $this->mt5Status = null;
            $this->orderTone = PropRiskVerdict::Block->value;
            $this->orderMessages = [$error];

            return;
        }

        $account = app(Mt5AccountSession::class)->current();
        $this->mt5Login = $account?->login ?? $this->mt5Login;
        $this->mt5Server = (string) ($account?->server ?? $this->mt5Server);
        $this->mt5Status = __('trading.terminal.mt5_connected', ['login' => $this->mt5Login]);
        $this->orderTone = null;
        $this->orderMessages = [];
    }

    public function disconnectMt5(Mt5AccountSession $session): void
    {
        $session->logout();
        $this->mt5Status = null;
    }

    public function login(PropAccountSession $session): void
    {
        if (! is_numeric($this->accountId)) {
            return;
        }

        $account = PropAccount::query()->find((int) $this->accountId);

        if ($account === null) {
            return;
        }

        $session->login($account);
        $this->accountId = (string) $account->id;
        $this->resetRiskCheck();
    }

    public function logout(PropAccountSession $session): void
    {
        $session->logout();
        $this->accountId = null;
        $this->resetRiskCheck();
    }

    public function checkRisk(PropRiskGuard $guard, PropAccountSession $session): void
    {
        $account = $session->current();
        $rule = $account?->rule;

        if ($account === null || $rule === null) {
            $this->riskVerdict = PropRiskVerdict::Block->value;
            $this->riskMessages = [__('trading.prop.risk.missing_rules')];

            return;
        }

        $risk = (float) $this->riskPercent;

        if (! $guard->acceptsOverride($rule, $risk)) {
            $bounds = $guard->overrideBounds($rule);
            $this->riskVerdict = PropRiskVerdict::Block->value;
            $this->riskMessages = [__('trading.prop.risk.override_bounds', [
                'min' => number_format($bounds['min'], 2),
                'max' => number_format($bounds['max'], 2),
            ])];

            return;
        }

        $session->rememberRisk($risk);
        $decision = $guard->assess($account, $risk, $this->stopPlaced);
        $this->riskVerdict = $decision->verdict->value;
        $this->riskMessages = $decision->messages;
    }

    /**
     * @return Collection<int, PropAccount>
     */
    public function accounts(): Collection
    {
        return PropAccount::query()
            ->orderBy('firm_name')
            ->orderBy('account_number')
            ->get();
    }

    public function activeAccount(): ?PropAccount
    {
        return app(PropAccountSession::class)->current();
    }

    public function liveAccount(): ?Mt5Account
    {
        return app(Mt5AccountSession::class)->current();
    }

    /**
     * @return list<array{key: string, label: string, display: string, width: float, meta: string, tone: string}>
     */
    public function safetyMeters(?PropAccount $account): array
    {
        return app(PropAccountMeters::class)->forAccount($account, (float) $this->riskPercent);
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
    public function desk(): array
    {
        $live = $this->liveAccount();

        if ($live !== null) {
            return app(TerminalDesk::class)->presentLive($live);
        }

        return app(TerminalDesk::class)->present($this->activeAccount());
    }

    public function selectSymbol(string $symbol): void
    {
        $this->orderSymbol = strtoupper(trim($symbol));
    }

    public function buy(TerminalOrderDispatcher $dispatcher): void
    {
        $this->send($dispatcher, TerminalOrderSide::Buy);
    }

    public function sell(TerminalOrderDispatcher $dispatcher): void
    {
        $this->send($dispatcher, TerminalOrderSide::Sell);
    }

    public function placeQuote(string $symbol, string $side, string $lots, TerminalOrderDispatcher $dispatcher): void
    {
        $enum = TerminalOrderSide::tryFrom(strtolower($side));

        if ($enum === null) {
            return;
        }

        $live = $this->liveAccount();

        if ($live === null) {
            $this->orderTone = PropRiskVerdict::Block->value;
            $this->orderMessages = [__('trading.terminal.need_session')];

            return;
        }

        $result = $dispatcher->placeLive($live, $symbol, $enum, $lots, '', '', true);
        $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
        $this->orderMessages = $result->messages;
    }

    /**
     * @return array{categories: list<string>, symbols: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        if (app()->runningUnitTests()) {
            return ['categories' => [], 'symbols' => []];
        }

        return app(Mt5BridgeClient::class)->symbols();
    }

    /**
     * @return array<string, mixed>
     */
    public function accountStrip(): array
    {
        if (app()->runningUnitTests()) {
            return [];
        }

        return app(Mt5BridgeClient::class)->account() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingOrders(): array
    {
        if (app()->runningUnitTests()) {
            return [];
        }

        return app(Mt5BridgeClient::class)->pending();
    }

    /**
     * @param  list<array{symbol: string, bid: string, ask: string}>  $quotes
     * @return list<array{symbol: string, bid: string, ask: string}>
     */
    public function watchRows(array $quotes): array
    {
        if ($this->watchlist === []) {
            return $quotes;
        }

        $wanted = array_map(strtoupper(...), $this->watchlist);
        $rows = [];

        foreach ($quotes as $quote) {
            $symbol = strtoupper((string) ($quote['symbol'] ?? ''));

            if (in_array($symbol, $wanted, true)) {
                $rows[$symbol] = $quote;
            }
        }

        foreach ($wanted as $symbol) {
            if (! isset($rows[$symbol])) {
                $rows[$symbol] = ['symbol' => $symbol, 'bid' => '—', 'ask' => '—'];
            }
        }

        return array_values($rows);
    }

    public function showCategory(string $category): void
    {
        $category = trim($category);

        if ($category === '' || strlen($category) > 40) {
            return;
        }

        $this->category = $category;
        $this->boardRows = app(Mt5BridgeClient::class)->board($category);
    }

    public function addWatch(string $symbol): void
    {
        $symbol = $this->cleanSymbol($symbol);

        if ($symbol === null) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.invalid_symbol'));

            return;
        }

        if (in_array($symbol, $this->watchlist, true)) {
            return;
        }

        if (count($this->watchlist) >= 10) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.watch_limit'));

            return;
        }

        $this->watchlist[] = $symbol;
        $this->pushWatchlist();
    }

    public function removeWatch(string $symbol): void
    {
        $symbol = $this->cleanSymbol($symbol);

        if ($symbol === null) {
            return;
        }

        $this->watchlist = array_values(array_filter(
            $this->watchlist,
            fn (string $item): bool => $item !== $symbol,
        ));
        $this->pushWatchlist();
    }

    public function selectChart(string $symbol): void
    {
        $clean = $this->cleanSymbol($symbol);

        if ($clean === null) {
            return;
        }

        $this->orderSymbol = $clean;
        $this->loadCandles($this->candleFrame);
    }

    public function loadCandles(string $timeframe): void
    {
        $frame = strtolower(trim($timeframe));
        $allowed = ['1m', '5m', '15m', '30m', '1h', '4h', 'd', 'w'];

        if (! in_array($frame, $allowed, true)) {
            return;
        }

        $symbol = $this->cleanSymbol($this->orderSymbol !== '' ? $this->orderSymbol : ($this->watchlist[0] ?? ''));

        if ($symbol === null) {
            return;
        }

        $this->candleFrame = $frame;
        $this->orderSymbol = $symbol;
        $this->candles = app(Mt5BridgeClient::class)->candles($symbol, $frame);
    }

    /**
     * @return list<array{x: float, body: float, high: float, low: float, top: float, height: float, up: bool, close: string, time: string}>
     */
    public function candleBars(): array
    {
        $bars = array_slice($this->candles, -40);

        if ($bars === []) {
            return [];
        }

        $highs = array_map(fn (array $bar): float => (float) ($bar['high'] ?? 0), $bars);
        $lows = array_map(fn (array $bar): float => (float) ($bar['low'] ?? 0), $bars);
        $top = max($highs);
        $bottom = min($lows);
        $span = max($top - $bottom, 0.00001);
        $points = [];

        foreach ($bars as $index => $bar) {
            $open = (float) ($bar['open'] ?? 0);
            $close = (float) ($bar['close'] ?? 0);
            $high = (float) ($bar['high'] ?? 0);
            $low = (float) ($bar['low'] ?? 0);
            $openY = (1 - (($open - $bottom) / $span)) * 100;
            $closeY = (1 - (($close - $bottom) / $span)) * 100;

            $points[] = [
                'x' => $index + 0.5,
                'body' => $index + 0.18,
                'high' => (1 - (($high - $bottom) / $span)) * 100,
                'low' => (1 - (($low - $bottom) / $span)) * 100,
                'top' => min($openY, $closeY),
                'height' => max(abs($openY - $closeY), 0.8),
                'up' => $close >= $open,
                'close' => (string) ($bar['close'] ?? ''),
                'time' => (string) ($bar['time'] ?? ''),
            ];
        }

        return $points;
    }

    public function placePendingOrder(): void
    {
        $live = $this->liveAccount();

        if ($live === null) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.need_session'));

            return;
        }

        $symbol = $this->cleanSymbol($this->pendingSymbol);
        $types = ['buy_limit', 'sell_limit', 'buy_stop', 'sell_stop'];

        if ($symbol === null || ! in_array($this->pendingType, $types, true) || ! is_numeric($this->pendingLots) || ! is_numeric($this->pendingPrice)) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.pending_invalid'));

            return;
        }

        $body = app(Mt5BridgeClient::class)->placePending(
            $symbol,
            $this->pendingType,
            (float) $this->pendingLots,
            (float) $this->pendingPrice,
            is_numeric($this->pendingStop) ? (float) $this->pendingStop : null,
            is_numeric($this->pendingTake) ? (float) $this->pendingTake : null,
        );

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            $message = is_array($body) && is_string($body['message'] ?? null)
                ? $body['message']
                : __('trading.terminal.mt5_unreachable');
            $this->note(PropRiskVerdict::Block, $message);

            return;
        }

        $this->note(PropRiskVerdict::Allow, __('trading.terminal.pending_placed', [
            'ticket' => '#'.($body['ticket'] ?? ''),
        ]));
    }

    public function cancelPendingOrder(int $ticket): void
    {
        $body = app(Mt5BridgeClient::class)->cancelPending($ticket);
        $ok = is_array($body) && ($body['ok'] ?? false) === true;
        $this->note(
            $ok ? PropRiskVerdict::Allow : PropRiskVerdict::Block,
            $ok ? __('trading.terminal.pending_cancelled') : (is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : __('trading.terminal.mt5_unreachable')),
        );
    }

    public function changeProtection(int $orderId, string $stop, string $take): void
    {
        $live = $this->liveAccount();
        $order = $live?->orders()->whereKey($orderId)->where('status', TerminalOrderStatus::Open)->first();

        if ($order === null || $order->mt5_ticket === null) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.missing_order'));

            return;
        }

        $body = app(Mt5BridgeClient::class)->modifyPosition(
            $order->mt5_ticket,
            is_numeric($stop) ? (float) $stop : null,
            is_numeric($take) ? (float) $take : null,
        );

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            $this->note(PropRiskVerdict::Block, is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : __('trading.terminal.mt5_unreachable'));

            return;
        }

        $order->fill([
            'stop_loss' => is_numeric($stop) ? $stop : null,
            'take_profit' => is_numeric($take) ? $take : null,
        ])->save();
        $this->note(PropRiskVerdict::Allow, __('trading.terminal.protection_saved'));
    }

    public function partialClose(int $orderId, string $lots, TerminalOrderDispatcher $dispatcher): void
    {
        $live = $this->liveAccount();

        if ($live === null) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.need_session'));

            return;
        }

        $result = $dispatcher->partialCloseLive($live, $orderId, $lots);
        $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
        $this->orderMessages = $result->messages;
    }

    public function loadHistory(): void
    {
        $days = is_numeric($this->historyDays) ? (int) $this->historyDays : 30;
        $this->historyDays = (string) max(1, min(365, $days));
        $this->archive = app(Mt5BridgeClient::class)->history((int) $this->historyDays);
    }

    public function sizeLots(): void
    {
        $symbol = $this->cleanSymbol($this->orderSymbol);

        if ($symbol === null) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.choose_symbol'));

            return;
        }

        $spec = null;

        foreach (app(Mt5BridgeClient::class)->quotes() as $quote) {
            if (strtoupper((string) ($quote['symbol'] ?? '')) === $symbol) {
                $spec = $quote;
                break;
            }
        }

        $account = app(Mt5BridgeClient::class)->account();
        $equity = is_array($account) && is_numeric($account['equity'] ?? null) ? (float) $account['equity'] : 0.0;
        $tickSize = is_array($spec) && is_numeric($spec['tick_size'] ?? null) ? (float) $spec['tick_size'] : 0.0;
        $tickValue = is_array($spec) && is_numeric($spec['tick_value'] ?? null) ? (float) $spec['tick_value'] : 0.0;
        $step = is_array($spec) && is_numeric($spec['volume_step'] ?? null) ? (float) $spec['volume_step'] : 0.01;
        $minimum = is_array($spec) && is_numeric($spec['volume_min'] ?? null) ? (float) $spec['volume_min'] : $step;
        $maximum = is_array($spec) && is_numeric($spec['volume_max'] ?? null) ? (float) $spec['volume_max'] : 500.0;
        $points = is_numeric($this->stopPoints) ? (float) $this->stopPoints : 0.0;

        if ($equity <= 0 || $tickSize <= 0 || $tickValue <= 0 || $points <= 0 || $step <= 0) {
            $this->note(PropRiskVerdict::Block, __('trading.terminal.lot_unavailable'));

            return;
        }

        $distance = $points * $tickSize;
        $perLot = ($tickValue / $tickSize) * $distance;
        $lots = ($equity * 0.01) / $perLot;
        $lots = round($lots / $step) * $step;
        $lots = min($maximum, max($minimum, $lots));
        $this->lots = number_format($lots, 2, '.', '');
        $this->orderSymbol = $symbol;
        $this->note(PropRiskVerdict::Allow, __('trading.terminal.lot_sized', ['lots' => $this->lots]));
    }

    public function closePosition(int $orderId, TerminalOrderDispatcher $dispatcher): void
    {
        $live = $this->liveAccount();

        if ($live !== null) {
            $result = $dispatcher->closeLive($live, $orderId);
            $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
            $this->orderMessages = $result->messages;

            return;
        }

        $account = app(PropAccountSession::class)->current();

        if ($account === null) {
            $this->orderTone = PropRiskVerdict::Block->value;
            $this->orderMessages = [__('trading.terminal.need_session')];

            return;
        }

        $result = $dispatcher->close($account, $orderId);
        $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
        $this->orderMessages = $result->messages;
    }

    private function send(TerminalOrderDispatcher $dispatcher, TerminalOrderSide $side): void
    {
        $live = $this->liveAccount();

        if ($live !== null) {
            $result = $dispatcher->placeLive(
                $live,
                $this->orderSymbol,
                $side,
                $this->lots,
                $this->stopLoss,
                $this->takeProfit,
            );
            $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
            $this->orderMessages = $result->messages;

            return;
        }

        $account = app(PropAccountSession::class)->current();

        if ($account === null) {
            $this->orderTone = PropRiskVerdict::Block->value;
            $this->orderMessages = [__('trading.terminal.need_session')];

            return;
        }

        $result = $dispatcher->place(
            $account,
            $this->orderSymbol,
            $side,
            $this->lots,
            $this->stopLoss,
            $this->takeProfit,
        );
        $this->orderTone = $result->verdict?->value ?? PropRiskVerdict::Block->value;
        $this->orderMessages = $result->messages;
    }

    private function fillRememberedPassword(Mt5Account $account): void
    {
        if (! $this->rememberMt5 || ! is_string($account->password) || $account->password === '') {
            return;
        }

        $this->mt5Password = $account->password;
    }

    private function storeRememberChoice(): void
    {
        session(['trading.terminal.remember_mt5' => $this->rememberMt5]);
        cookie()->queue(cookie('mt5_remember', $this->rememberMt5 ? '1' : '0', 60 * 24 * 400));
    }

    private function resetRiskCheck(): void
    {
        $this->riskVerdict = null;
        $this->riskMessages = [];
    }

    private function pushWatchlist(): void
    {
        session(['trading.terminal.watchlist' => $this->watchlist]);
        $body = app(Mt5BridgeClient::class)->replaceWatchlist($this->watchlist);
        $symbols = is_array($body) ? ($body['symbols'] ?? null) : null;

        if (! is_array($symbols)) {
            return;
        }

        $this->watchlist = array_values(array_filter(array_map(
            fn (mixed $symbol): string => strtoupper(trim((string) $symbol)),
            $symbols,
        )));
        session(['trading.terminal.watchlist' => $this->watchlist]);
    }

    private function cleanSymbol(string $symbol): ?string
    {
        $symbol = strtoupper(trim($symbol));

        if (preg_match('/^[A-Z0-9._]{2,24}$/', $symbol) !== 1) {
            return null;
        }

        return $symbol;
    }

    private function note(PropRiskVerdict $tone, string $message): void
    {
        $this->orderTone = $tone->value;
        $this->orderMessages = [$message];
    }
}
