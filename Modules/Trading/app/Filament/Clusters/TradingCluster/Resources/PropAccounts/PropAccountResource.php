<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts;

use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Trading\Filament\Clusters\TradingCluster;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\CreatePropAccount;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\EditPropAccount;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\ListPropAccounts;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Schemas\PropAccountForm;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Tables\PropAccountsTable;
use Modules\Trading\Models\PropAccount;

class PropAccountResource extends Resource
{
    protected static ?string $model = PropAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = TradingCluster::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'firm_name';

    public static function getNavigationLabel(): string
    {
        return __('trading.menu.prop_accounts');
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => __('trading.nav.prop_accounts'),
            ], merge: true);
        }

        return $items;
    }

    public static function getModelLabel(): string
    {
        return __('trading.prop.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('trading.nav.prop_accounts');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('rule');
    }

    public static function form(Schema $schema): Schema
    {
        return PropAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropAccounts::route('/'),
            'create' => CreatePropAccount::route('/create'),
            'edit' => EditPropAccount::route('/{record}/edit'),
        ];
    }
}
