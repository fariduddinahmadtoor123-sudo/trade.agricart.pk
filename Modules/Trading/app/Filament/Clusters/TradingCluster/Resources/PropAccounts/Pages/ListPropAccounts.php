<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns\HidesPropPageHeader;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\PropAccountResource;

class ListPropAccounts extends ListRecords
{
    use HidesPropPageHeader;

    protected static string $resource = PropAccountResource::class;
}
