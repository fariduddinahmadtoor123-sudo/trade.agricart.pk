<?php

namespace Modules\Trading\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Trading\Database\Factories\PropAccountRuleFactory;
use Modules\Trading\Enums\DailyDrawdownType;
use Modules\Trading\Enums\MaxDrawdownBasis;
use Modules\Trading\Enums\PropPayoutStyle;
use Modules\Trading\Enums\PropRuleSource;
use Modules\Trading\Enums\PropStopLossMode;

#[UseFactory(PropAccountRuleFactory::class)]
#[Fillable([
    'prop_account_id',
    'source',
    'profit_target_percent',
    'daily_drawdown_percent',
    'daily_drawdown_type',
    'max_total_drawdown_percent',
    'max_drawdown_basis',
    'max_loss_per_trade_percent',
    'minimum_trading_days',
    'phase_two_profit_target_percent',
    'leverage',
    'stop_loss_mode',
    'payout_style',
    'stop_loss_required',
    'stop_loss_notes',
])]
class PropAccountRule extends Model
{
    /** @use HasFactory<PropAccountRuleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'manual',
        'stop_loss_mode' => 'optional',
        'stop_loss_required' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PropRuleSource::class,
            'profit_target_percent' => 'decimal:2',
            'daily_drawdown_percent' => 'decimal:2',
            'daily_drawdown_type' => DailyDrawdownType::class,
            'max_total_drawdown_percent' => 'decimal:2',
            'max_drawdown_basis' => MaxDrawdownBasis::class,
            'max_loss_per_trade_percent' => 'decimal:2',
            'minimum_trading_days' => 'integer',
            'phase_two_profit_target_percent' => 'decimal:2',
            'stop_loss_mode' => PropStopLossMode::class,
            'payout_style' => PropPayoutStyle::class,
            'stop_loss_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PropAccountRule $rule): void {
            if ($rule->stop_loss_mode instanceof PropStopLossMode) {
                $rule->stop_loss_required = $rule->stop_loss_mode->requiresStop();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PropAccount::class, 'prop_account_id');
    }
}
