<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns\HidesPropPageHeader;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns\SyncsFundedHiveRules;
use Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\PropAccountResource;

class EditPropAccount extends EditRecord
{
    use HidesPropPageHeader;
    use SyncsFundedHiveRules;

    protected static string $resource = PropAccountResource::class;
}
