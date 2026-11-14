<?php

namespace App\Filament\Clusters\SettingsCluster\Resources\PageInfos;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages\CreatePageInfo;
use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages\EditPageInfo;
use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages\ListPageInfos;
use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Schemas\PageInfoForm;
use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Tables\PageInfosTable;
use App\Models\PageInfo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PageInfoResource extends Resource
{
    protected static ?string $model = PageInfo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $navigationLabel = 'Page Info';

    protected static ?string $modelLabel = 'page info';

    protected static ?string $pluralModelLabel = 'page info';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('trading.nav.page_info');
    }

    public static function getModelLabel(): string
    {
        return __('trading.nav.page_info');
    }

    public static function getPluralModelLabel(): string
    {
        return __('trading.nav.page_info');
    }

    public static function form(Schema $schema): Schema
    {
        return PageInfoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageInfosTable::configure($table);
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
            'index' => ListPageInfos::route('/'),
            'create' => CreatePageInfo::route('/create'),
            'edit' => EditPageInfo::route('/{record}/edit'),
        ];
    }
}
