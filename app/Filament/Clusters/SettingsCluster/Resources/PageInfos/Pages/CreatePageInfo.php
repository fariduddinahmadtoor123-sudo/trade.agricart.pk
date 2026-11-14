<?php

namespace App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Pages;

use App\Filament\Clusters\SettingsCluster\Resources\PageInfos\PageInfoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePageInfo extends CreateRecord
{
    protected static string $resource = PageInfoResource::class;
}
