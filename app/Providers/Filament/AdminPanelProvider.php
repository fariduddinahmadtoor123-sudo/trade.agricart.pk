<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('AgriCart')
            ->font('Arial', provider: LocalFontProvider::class)
            ->colors([
                'primary' => Color::hex('#10B981'),
                'success' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->sidebarWidth('14.5rem')
            ->collapsedSidebarWidth('4.25rem')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->subNavigationPosition(SubNavigationPosition::Top)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: app_path('Filament/Clusters/SettingsCluster/Resources'), for: 'App\\Filament\\Clusters\\SettingsCluster\\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverClusters(in: base_path('Modules/Trading/app/Filament/Clusters'), for: 'Modules\\Trading\\Filament\\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverPages(in: base_path('Modules/Trading/app/Filament/Pages'), for: 'Modules\\Trading\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->discoverWidgets(in: base_path('Modules/Trading/app/Filament/Widgets'), for: 'Modules\\Trading\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(null),
                FilamentApexChartsPlugin::make(),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                function (): string {
                    $path = public_path('css/filament/admin-layout.css');
                    $version = is_file($path) ? (string) filemtime($path) : (string) time();

                    return Blade::render(
                        '<link rel="stylesheet" href="{{ asset(\'css/filament/admin-layout.css\') }}?v={{ $version }}">',
                        ['version' => $version],
                    );
                },
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('@include(\'filament.hooks.quick-actions\')@include(\'filament.hooks.locale-switch\')'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    if (! request()->is('admin/trading', 'admin/trading/*')) {
                        return '';
                    }

                    $path = public_path('css/filament/page-info.css');
                    $version = is_file($path) ? (string) filemtime($path) : (string) time();

                    return '<link rel="stylesheet" href="'.e(asset('css/filament/page-info.css')).'?v='.$version.'">'
                        .Blade::render('@livewire(\'page-info-window\')');
                },
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->persistentMiddleware([
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
