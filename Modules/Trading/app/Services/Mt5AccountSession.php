<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\Mt5Account;

class Mt5AccountSession
{
    public const KEY = 'trading.mt5_account_id';

    public function id(): ?int
    {
        $id = session(self::KEY);

        if (! is_numeric($id)) {
            return null;
        }

        return (int) $id;
    }

    public function current(): ?Mt5Account
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return Mt5Account::query()->find($id);
    }

    public function login(Mt5Account $account): void
    {
        session([self::KEY => $account->id]);
    }

    public function logout(): void
    {
        session()->forget(self::KEY);
    }
}
