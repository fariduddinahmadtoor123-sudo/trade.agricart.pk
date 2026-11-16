<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns\HidesPropPageHeader;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns\SyncsFundedHiveRules;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\PropAccountResource;

class CreatePropAccount extends CreateRecord
{
    use HidesPropPageHeader;
    use SyncsFundedHiveRules;

    protected static string $resource = PropAccountResource::class;
}
