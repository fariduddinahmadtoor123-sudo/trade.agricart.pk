<?php

namespace App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages;

use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\PageInfoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPageInfo extends EditRecord
{
    protected static string $resource = PageInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
