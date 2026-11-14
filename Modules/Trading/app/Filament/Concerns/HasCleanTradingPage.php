<?php

namespace Modules\Trading\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

trait HasCleanTradingPage
{
    public static function getNavigationLabel(): string|Htmlable
    {
        return __('trading.nav.'.static::$translationKey);
    }

    public function getTitle(): string|Htmlable
    {
        return __('trading.nav.'.static::$translationKey);
    }

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
}
