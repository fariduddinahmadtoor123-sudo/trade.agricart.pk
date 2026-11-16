<?php

namespace Modules\Trading\Services;

use Modules\Trading\Models\PropAccount;

class PropBridgeSnapshot
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function capture(?array $payload): ?PropAccount
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

        $account = $accounts->first();

        if ($account === null) {
            return null;
        }

        $updates = [];

        foreach ([
            'balance' => 'current_balance',
            'equity' => 'equity',
            'day_start_balance' => 'day_start_balance',
            'today_pnl' => 'today_pnl',
            'floating_pnl' => 'floating_pnl',
        ] as $source => $column) {
            $value = $payload[$source] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $updates[$column] = $value;
        }

        if ($updates === []) {
            return $account;
        }

        $updates['snapshot_at'] = now();
        $account->fill($updates)->save();

        return $account->refresh();
    }
}
