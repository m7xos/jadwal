<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AgendaPerHariChart;
use App\Filament\Widgets\AgendaStatsOverview;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelServiceProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelServiceProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login() // atau ->authGuard('web') sesuai setelanmu
            ->colors([
                'primary' => Color::Sky,
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->widgets([
                AgendaStatsOverview::class,   // ✅ koma di sini
                AgendaPerHariChart::class,    // ✅ tidak ada panah / method lagi di sini
            ])
            ->navigation(function (NavigationBuilder $navigation): NavigationBuilder {
                return $navigation
                    ->groups([
                        NavigationGroup::make('Manajemen Kegiatan')
                            ->icon('heroicon-o-calendar-days'),
                        NavigationGroup::make('Master Data')
                            ->icon('heroicon-o-archive-box'),
                        NavigationGroup::make('Pengaturan')
                            ->icon('heroicon-o-cog-6-tooth'),
                    ]);
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
