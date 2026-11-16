<?php

namespace Modules\Trading\Filament\Concerns;

use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Support\Htmlable;

trait HasCleanTradingPage
{
    public static function getNavigationLabel(): string|Htmlable
    {
        return __('trading.menu.'.static::$translationKey);
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        foreach ($items as $item) {
            $item->extraAttributes([
                'title' => __('trading.nav.'.static::$translationKey),
            ], merge: true);
        }

        return $items;
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
