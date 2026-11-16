<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\PropAccount;

class PropAccountSession
{
    public const KEY = 'trading.prop_account_id';

    public const RISK_KEY = 'trading.prop_risk_percent';

    public function id(): ?int
    {
        $id = session(self::KEY);

        if (! is_numeric($id)) {
            return null;
        }

        return (int) $id;
    }

    public function current(): ?PropAccount
    {
        $id = $this->id();

        if ($id === null) {
            return null;
        }

        return PropAccount::query()->with('rule')->find($id);
    }

    public function login(PropAccount $account): void
    {
        session([self::KEY => $account->id]);
    }

    public function riskPercent(): ?float
    {
        $percent = session(self::RISK_KEY);

        return is_numeric($percent) ? (float) $percent : null;
    }

    public function rememberRisk(float $percent): void
    {
        session([self::RISK_KEY => $percent]);
    }

    public function logout(): void
    {
        session()->forget([self::KEY, self::RISK_KEY]);
    }
}
