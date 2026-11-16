<?php

namespace Modules\Trading\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMt5SessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account' => ['required', 'array'],
            'account.account_number' => ['required', 'string', 'max:64'],
            'account.firm_name' => ['nullable', 'string', 'max:255'],
            'account.balance' => ['nullable', 'numeric'],
            'account.equity' => ['nullable', 'numeric'],
            'account.day_start_balance' => ['nullable', 'numeric'],
            'account.today_pnl' => ['nullable', 'numeric'],
            'account.floating_pnl' => ['nullable', 'numeric'],
            'ticks' => ['nullable', 'array', 'max:100'],
            'ticks.*.symbol' => ['required', 'string', 'max:32'],
            'ticks.*.bid' => ['nullable', 'numeric'],
            'ticks.*.ask' => ['nullable', 'numeric'],
            'positions' => ['nullable', 'array', 'max:200'],
            'positions.*.ticket' => ['required', 'integer', 'min:1'],
            'positions.*.symbol' => ['required', 'string', 'max:32'],
            'positions.*.side' => ['required', 'in:buy,sell'],
            'positions.*.lots' => ['required', 'numeric', 'gt:0'],
            'positions.*.price_open' => ['required', 'numeric'],
            'positions.*.sl' => ['nullable', 'numeric'],
            'positions.*.tp' => ['nullable', 'numeric'],
            'positions.*.profit' => ['nullable', 'numeric'],
            'positions.*.client_order_id' => ['nullable', 'integer', 'min:1'],
            'positions.*.opened_at' => ['nullable', 'date'],
            'deals' => ['nullable', 'array', 'max:200'],
            'deals.*.position_ticket' => ['required', 'integer', 'min:1'],
            'deals.*.symbol' => ['required', 'string', 'max:32'],
            'deals.*.side' => ['required', 'in:buy,sell'],
            'deals.*.lots' => ['required', 'numeric', 'gt:0'],
            'deals.*.price' => ['required', 'numeric'],
            'deals.*.profit' => ['required', 'numeric'],
            'deals.*.entry' => ['required', 'in:in,out,out_by,inout'],
            'deals.*.time' => ['nullable', 'date'],
        ];
    }
}
