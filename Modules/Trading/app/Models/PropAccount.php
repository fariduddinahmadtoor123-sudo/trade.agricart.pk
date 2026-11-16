<?php

namespace Modules\Trading\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Trading\Database\Factories\PropAccountFactory;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropFundingModel;
use Modules\Trading\Enums\PropPlatform;
use Modules\Trading\Enums\PropRiskCategory;

#[UseFactory(PropAccountFactory::class)]
#[Fillable([
    'firm_name',
    'account_number',
    'platform',
    'status',
    'model_type',
    'phase',
    'risk_category',
    'initial_balance',
    'current_balance',
    'equity',
    'day_start_balance',
    'today_pnl',
    'floating_pnl',
    'snapshot_at',
])]
class PropAccount extends Model
{
    /** @use HasFactory<PropAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => PropPlatform::class,
            'status' => PropAccountStatus::class,
            'model_type' => PropFundingModel::class,
            'phase' => 'integer',
            'risk_category' => PropRiskCategory::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'equity' => 'decimal:2',
            'day_start_balance' => 'decimal:2',
            'today_pnl' => 'decimal:2',
            'floating_pnl' => 'decimal:2',
            'snapshot_at' => 'datetime',
        ];
    }

    public function rule(): HasOne
    {
        return $this->hasOne(PropAccountRule::class);
    }

    /**
     * @return HasMany<TerminalOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(TerminalOrder::class);
    }

    public function sessionLabel(): string
    {
        return $this->firm_name.' · '.$this->account_number;
    }

    public function activePhase(): int
    {
        if ($this->model_type !== PropFundingModel::TwoStep || (int) $this->phase !== 2) {
            return 1;
        }

        return 2;
    }

    public function activeProfitTarget(): float
    {
        if ($this->model_type instanceof PropFundingModel) {
            return $this->model_type->activeTarget($this->activePhase());
        }

        return is_numeric($this->rule?->profit_target_percent)
            ? (float) $this->rule->profit_target_percent
            : 0.0;
    }

    public function activeLossCap(): float
    {
        if ($this->status === PropAccountStatus::Funded) {
            return $this->risk_category->maxRiskPercent();
        }

        if ($this->model_type instanceof PropFundingModel) {
            return $this->model_type->rules()['max_loss_per_trade'];
        }

        return is_numeric($this->rule?->max_loss_per_trade_percent)
            ? (float) $this->rule->max_loss_per_trade_percent
            : 0.0;
    }
}
