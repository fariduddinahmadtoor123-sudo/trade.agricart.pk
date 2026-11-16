<?php

namespace Modules\Trading\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Trading\Database\Factories\TerminalOrderFactory;
use Modules\Trading\Enums\TerminalOrderSide;
use Modules\Trading\Enums\TerminalOrderStatus;

#[UseFactory(TerminalOrderFactory::class)]
#[Fillable([
    'prop_account_id',
    'mt5_account_id',
    'symbol',
    'side',
    'lots',
    'stop_loss',
    'take_profit',
    'entry_price',
    'exit_price',
    'status',
    'realized_pnl',
    'mt5_ticket',
    'floating_pnl',
    'bridge_message',
    'opened_at',
    'closed_at',
])]
class TerminalOrder extends Model
{
    /** @use HasFactory<TerminalOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => TerminalOrderSide::class,
            'lots' => 'decimal:2',
            'stop_loss' => 'decimal:8',
            'take_profit' => 'decimal:8',
            'entry_price' => 'decimal:8',
            'exit_price' => 'decimal:8',
            'status' => TerminalOrderStatus::class,
            'realized_pnl' => 'decimal:2',
            'mt5_ticket' => 'integer',
            'floating_pnl' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PropAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PropAccount::class, 'prop_account_id');
    }

    /**
     * @return BelongsTo<Mt5Account, $this>
     */
    public function liveAccount(): BelongsTo
    {
        return $this->belongsTo(Mt5Account::class, 'mt5_account_id');
    }
}
