<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Trading\Enums\DailyDrawdownType;
use Modules\Trading\Enums\MaxDrawdownBasis;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropFundingModel;
use Modules\Trading\Enums\PropPayoutStyle;
use Modules\Trading\Enums\PropPlatform;
use Modules\Trading\Enums\PropRiskCategory;
use Modules\Trading\Enums\PropRuleSource;
use Modules\Trading\Enums\PropStopLossMode;

class PropAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trading.prop.sections.account'))
                    ->schema([
                        TextInput::make('firm_name')
                            ->label(__('trading.prop.fields.firm_name'))
                            ->placeholder('FundedHive')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('account_number')
                            ->label(__('trading.prop.fields.account_number'))
                            ->required()
                            ->maxLength(255)
                            ->scopedUnique(modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                return $query->where('firm_name', $get('firm_name'));
                            }),
                        Select::make('platform')
                            ->label(__('trading.prop.fields.platform'))
                            ->options(PropPlatform::class)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('trading.prop.fields.status'))
                            ->options(PropAccountStatus::class)
                            ->required()
                            ->native(false),
                        Select::make('model_type')
                            ->label(__('trading.prop.fields.model_type'))
                            ->options(PropFundingModel::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (PropFundingModel|string|null $state, Set $set, Get $get): void {
                                self::applyFundingModel($state, $get('phase'), $get('risk_category'), $set);
                            }),
                        Select::make('phase')
                            ->label(__('trading.prop.fields.phase'))
                            ->options([
                                1 => __('trading.prop.phases.1'),
                                2 => __('trading.prop.phases.2'),
                            ])
                            ->default(1)
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get): bool => self::fundingModel($get('model_type')) === PropFundingModel::TwoStep)
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                self::applyFundingModel($get('model_type'), $state, $get('risk_category'), $set);
                            }),
                        Select::make('risk_category')
                            ->label(__('trading.prop.fields.risk_category'))
                            ->options(PropRiskCategory::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (PropRiskCategory|string|null $state, Set $set, Get $get): void {
                                self::applyFundingModel($get('model_type'), $get('phase'), $state, $set);
                            }),
                        TextInput::make('initial_balance')
                            ->label(__('trading.prop.fields.initial_balance'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('$'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('trading.prop.sections.rules'))
                    ->description(__('trading.prop.sections.rules_help'))
                    ->relationship('rule')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                        ...self::normalizeRule($data),
                        'source' => PropRuleSource::Manual->value,
                    ])
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::normalizeRule($data))
                    ->schema([
                        TextInput::make('phase_two_profit_target_percent')
                            ->label(__('trading.prop.fields.phase_two_target'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => self::fundingModel($get('../model_type')) === PropFundingModel::TwoStep),
                        TextInput::make('profit_target_percent')
                            ->label(__('trading.prop.fields.profit_target'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('daily_drawdown_percent')
                            ->label(__('trading.prop.fields.daily_drawdown'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        Select::make('daily_drawdown_type')
                            ->label(__('trading.prop.fields.daily_drawdown_type'))
                            ->options(DailyDrawdownType::class)
                            ->required()
                            ->native(false),
                        Select::make('max_drawdown_basis')
                            ->label(__('trading.prop.fields.max_drawdown_basis'))
                            ->options(MaxDrawdownBasis::class)
                            ->disabled()
                            ->dehydrated()
                            ->native(false),
                        TextInput::make('max_total_drawdown_percent')
                            ->label(__('trading.prop.fields.max_total_drawdown'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('max_loss_per_trade_percent')
                            ->label(__('trading.prop.fields.max_loss_per_trade'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('minimum_trading_days')
                            ->label(__('trading.prop.fields.minimum_trading_days'))
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365),
                        TextInput::make('leverage')
                            ->label(__('trading.prop.fields.leverage'))
                            ->placeholder('1:100')
                            ->required()
                            ->maxLength(32),
                        Select::make('stop_loss_mode')
                            ->label(__('trading.prop.fields.stop_loss_mode'))
                            ->options(PropStopLossMode::class)
                            ->disabled()
                            ->dehydrated()
                            ->native(false),
                        Select::make('payout_style')
                            ->label(__('trading.prop.fields.payout_style'))
                            ->options(PropPayoutStyle::class)
                            ->disabled()
                            ->dehydrated()
                            ->native(false),
                        Textarea::make('stop_loss_notes')
                            ->label(__('trading.prop.fields.stop_loss_notes'))
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeRule(array $data): array
    {
        $mode = $data['stop_loss_mode'] ?? null;
        $mode = $mode instanceof PropStopLossMode
            ? $mode
            : (PropStopLossMode::tryFrom((string) $mode) ?? PropStopLossMode::Optional);

        return [
            ...$data,
            'stop_loss_mode' => $mode->value,
            'stop_loss_required' => $mode->requiresStop(),
        ];
    }

    private static function applyFundingModel(PropFundingModel|string|null $modelState, mixed $phase, PropRiskCategory|string|null $riskState, Set $set): void
    {
        $model = self::fundingModel($modelState);

        if ($model === null) {
            return;
        }

        $phaseNumber = $model === PropFundingModel::TwoStep && (int) $phase === 2 ? 2 : 1;
        $rules = $model->rules();

        $set('phase', $phaseNumber);
        $set('rule.profit_target_percent', $rules['phase_one_target']);
        $set('rule.phase_two_profit_target_percent', $rules['phase_two_target']);
        $set('rule.daily_drawdown_percent', $rules['daily_drawdown']);
        $set('rule.daily_drawdown_type', $rules['daily_drawdown_type']->value);
        $set('rule.max_total_drawdown_percent', $rules['max_drawdown']);
        $set('rule.max_drawdown_basis', $rules['max_drawdown_basis']->value);
        $set('rule.max_loss_per_trade_percent', $rules['max_loss_per_trade']);
        $set('rule.minimum_trading_days', $rules['minimum_trading_days']);

        $category = $riskState instanceof PropRiskCategory
            ? $riskState
            : PropRiskCategory::tryFrom((string) $riskState);

        if ($category === null) {
            return;
        }

        $set('rule.leverage', $category->leverage());
        $set('rule.stop_loss_mode', $category->stopLossMode()->value);
        $set('rule.payout_style', $category->payout()->value);
    }

    private static function fundingModel(PropFundingModel|string|null $state): ?PropFundingModel
    {
        return $state instanceof PropFundingModel
            ? $state
            : PropFundingModel::tryFrom((string) $state);
    }
}
