<?php

namespace Modules\Trading\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Trading\Enums\PropRiskVerdict;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Concerns\HasCleanTradingPage;
use Modules\Trading\Models\Mt5Account;
use Modules\Trading\Models\PropAccount;
use Modules\Trading\Services\Mt5AccountSession;
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

    public ?string $mt5Status = null;

    public function mount(PropAccountSession $session, Mt5AccountSession $live): void
    {
        $current = $session->id();

        $this->accountId = $current === null ? null : (string) $current;

        $remembered = $session->riskPercent();

        if ($remembered !== null) {
            $this->riskPercent = number_format($remembered, 2, '.', '');
        }

        $saved = $live->current();

        if ($saved !== null) {
            $this->mt5Login = $saved->login;
            $this->mt5Server = (string) $saved->server;
            $this->mt5Status = __('trading.terminal.mt5_connected', ['login' => $saved->login]);
        }

        if (app()->runningUnitTests()) {
            return;
        }

        $pulled = app(Mt5DeskConnection::class)->rememberFromTerminal();

        if ($pulled === null) {
            return;
        }

        $this->mt5Login = $pulled->login;
        $this->mt5Server = (string) $pulled->server;
        $this->mt5Status = __('trading.terminal.mt5_connected', ['login' => $pulled->login]);
    }

    public function connectMt5(Mt5DeskConnection $connection): void
    {
        $error = $connection->connect($this->mt5Login, $this->mt5Password, $this->mt5Server);
        $this->mt5Password = '';

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

    private function resetRiskCheck(): void
    {
        $this->riskVerdict = null;
        $this->riskMessages = [];
    }
}
