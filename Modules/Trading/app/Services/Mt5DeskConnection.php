<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\Mt5Account;

class Mt5DeskConnection
{
    public function __construct(
        private Mt5BridgeClient $bridge,
        private Mt5AccountSession $session,
    ) {}

    public function rememberFromTerminal(): ?Mt5Account
    {
        $health = $this->bridge->health();

        if ($health === null || ($health['ok'] ?? false) !== true || ! is_numeric($health['login'] ?? null)) {
            return null;
        }

        $account = $this->store((string) $health['login'], null, (string) ($health['server'] ?? ''), $health);
        $this->session->login($account);

        return $account;
    }

    public function connect(string $login, string $password, string $server): ?string
    {
        $login = trim($login);
        $server = trim($server);

        if ($login === '') {
            return __('trading.terminal.mt5_login_required');
        }

        $snapshot = $password !== '' || $server !== ''
            ? $this->bridge->signIn($login, $password, $server)
            : $this->bridge->health();

        if ($snapshot === null) {
            return __('trading.terminal.mt5_unreachable');
        }

        if (($snapshot['ok'] ?? false) !== true) {
            $message = is_string($snapshot['message'] ?? null) ? $snapshot['message'] : '';

            return $message !== '' ? $message : __('trading.terminal.mt5_login_failed');
        }

        if (! is_numeric($snapshot['login'] ?? null) || (string) $snapshot['login'] !== $login) {
            $open = is_numeric($snapshot['login'] ?? null) ? (string) $snapshot['login'] : '';

            return __('trading.terminal.mt5_wrong_login', ['login' => $open]);
        }

        $account = $this->store($login, $password !== '' ? $password : null, $server !== '' ? $server : (string) ($snapshot['server'] ?? ''), $snapshot);
        $this->session->login($account);

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function store(string $login, ?string $password, string $server, array $snapshot): Mt5Account
    {
        $account = Mt5Account::query()->firstOrNew(['login' => $login]);
        $account->fill([
            'server' => $server !== '' ? $server : $account->server,
            'company' => is_string($snapshot['company'] ?? null) ? $snapshot['company'] : $account->company,
            'holder_name' => is_string($snapshot['name'] ?? null) ? $snapshot['name'] : $account->holder_name,
            'balance' => is_numeric($snapshot['balance'] ?? null) ? $snapshot['balance'] : $account->balance,
            'equity' => is_numeric($snapshot['equity'] ?? null) ? $snapshot['equity'] : $account->equity,
            'snapshot_at' => now(),
            'connected_at' => now(),
        ]);

        if ($password !== null) {
            $account->password = $password;
        }

        $account->save();

        return $account->refresh();
    }
}
