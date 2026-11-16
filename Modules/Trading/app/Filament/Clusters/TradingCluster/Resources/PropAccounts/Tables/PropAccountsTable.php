<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Trading\Enums\PropAccountStatus;
use Modules\Trading\Enums\PropPlatform;
use Modules\Trading\Enums\PropRiskCategory;

class PropAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firm_name')
                    ->label(__('trading.prop.fields.firm_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_number')
                    ->label(__('trading.prop.fields.account_number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('initial_balance')
                    ->label(__('trading.prop.fields.initial_balance'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('platform')
                    ->label(__('trading.prop.fields.platform'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('model_type')
                    ->label(__('trading.prop.fields.model_type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('phase')
                    ->label(__('trading.prop.fields.phase'))
                    ->formatStateUsing(fn (mixed $state): string => __('trading.prop.phases.'.(int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('trading.prop.fields.status'))
                    ->badge()
                    ->color(fn (PropAccountStatus $state): string => match ($state) {
                        PropAccountStatus::Funded => 'success',
                        PropAccountStatus::Challenge => 'info',
                        PropAccountStatus::Breached => 'danger',
                        PropAccountStatus::Paused => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('risk_category')
                    ->label(__('trading.prop.fields.risk_category'))
                    ->badge()
                    ->color(fn (PropRiskCategory $state): string => match ($state) {
                        PropRiskCategory::Low => 'success',
                        PropRiskCategory::Moderate, PropRiskCategory::Medium => 'warning',
                        PropRiskCategory::High => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('rule.profit_target_percent')
                    ->label(__('trading.prop.fields.profit_target'))
                    ->suffix('%')
                    ->placeholder('—'),
                TextColumn::make('rule.daily_drawdown_percent')
                    ->label(__('trading.prop.fields.daily_drawdown'))
                    ->suffix('%')
                    ->placeholder('—'),
            ])
            ->defaultSort('firm_name')
            ->filters([
                SelectFilter::make('platform')
                    ->label(__('trading.prop.fields.platform'))
                    ->options(PropPlatform::class),
                SelectFilter::make('status')
                    ->label(__('trading.prop.fields.status'))
                    ->options(PropAccountStatus::class),
                SelectFilter::make('risk_category')
                    ->label(__('trading.prop.fields.risk_category'))
                    ->options(PropRiskCategory::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('trading.prop.terminal.manage')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
