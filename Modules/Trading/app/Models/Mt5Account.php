<?php

namespace Modules\Trading\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Trading\Database\Factories\Mt5AccountFactory;

#[UseFactory(Mt5AccountFactory::class)]
#[Fillable([
    'login',
    'password',
    'server',
    'company',
    'holder_name',
    'balance',
    'equity',
    'today_pnl',
    'floating_pnl',
    'snapshot_at',
    'connected_at',
])]
class Mt5Account extends Model
{
    /** @use HasFactory<Mt5AccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'today_pnl' => 'decimal:2',
            'floating_pnl' => 'decimal:2',
            'snapshot_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TerminalOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(TerminalOrder::class);
    }
}
