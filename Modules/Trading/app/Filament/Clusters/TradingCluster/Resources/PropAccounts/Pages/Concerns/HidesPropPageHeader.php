<?php

namespace Modules\Trading\Filament\Clusters\TradingCluster\Resources\PropAccounts\Pages\Concerns;

use Illuminate\Contracts\Support\Htmlable;

trait HidesPropPageHeader
{
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
