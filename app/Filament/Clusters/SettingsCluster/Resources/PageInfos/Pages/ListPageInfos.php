<?php

namespace App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages;

use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\PageInfoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageInfos extends ListRecords
{
    protected static string $resource = PageInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
