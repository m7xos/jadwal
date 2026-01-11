<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AgendaPerHariChart;
use App\Filament\Widgets\AgendaStatsOverview;
use App\Filament\Widgets\VehicleStatsOverview;
use App\Filament\Pages\LaporanSuratMasukBulanan;
use App\Filament\Pages\RoleAccessSettings;
use App\Filament\Auth\Login as PersonilLogin;
use App\Http\Middleware\EnsureRoleHasPageAccess;
use App\Support\RoleAccess;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use YieldStudio\FilamentPanel\Plugins\YieldPanel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // URL laporan bulanan yang sudah kamu tes: http://127.0.0.1/admin/laporan-surat-masuk-bulanan
        $laporanUrl = url('/admin/laporan-surat-masuk-bulanan');

        $yieldPanelPref = array_merge([
            'colors' => false,
            'font' => false,
            'icons' => true,
        ], (array) session('yieldpanel', []));

        $primaryPalette = $yieldPanelPref['colors']
            ? Color::generatePalette('#ff7f11') // lebih kontras agar perubahan terlihat
            : Color::Sky;

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(PersonilLogin::class)
            ->authGuard('personil')
            ->colors([
                'primary' => $primaryPalette,
            ])
            ->brandLogo(fn () => view('filament.brand-logo', ['variant' => 'light']))
            ->darkModeBrandLogo(fn () => view('filament.brand-logo', ['variant' => 'dark']))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/logo/favicon-16x16.png'))
            ->font($yieldPanelPref['font'] ? 'Inter' : null)
            ->plugin(
                YieldPanel::make()
                    ->withSuggestedColors(false)
                    ->withSuggestedFont(false)
                    ->withSuggestedIcons((bool) $yieldPanelPref['icons'])
            )
            ->userMenuItems([
                'yieldpanel' => Action::make('yieldpanel')
                    ->label('Tema Yield Panel')
                    ->icon('heroicon-o-swatch')
                    ->modalHeading('Tema Yield Panel')
                    ->modalSubmitActionLabel('Terapkan')
                    ->fillForm(fn () => $yieldPanelPref)
                    ->form([
                        \Filament\Forms\Components\Toggle::make('colors')
                            ->label('Warna disarankan'),
                        \Filament\Forms\Components\Toggle::make('font')
                            ->label('Gunakan font Inter (tema)'),
                        \Filament\Forms\Components\Toggle::make('icons')
                            ->label('Ikon Phosphor')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        session([
                            'yieldpanel' => [
                                'colors' => (bool) ($data['colors'] ?? false),
                                'font' => (bool) ($data['font'] ?? false),
                                'icons' => (bool) ($data['icons'] ?? false),
                            ],
                        ]);
                    })
                    ->successNotificationTitle('Tema diterapkan')
                    ->closeModalByClickingAway(false),
            ])
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages',
            )
            ->pages([
                Dashboard::class,
                RoleAccessSettings::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets',
            )
            ->widgets([
                AgendaStatsOverview::class,
                AgendaPerHariChart::class,
                VehicleStatsOverview::class,
            ])
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.partials.wa-inbox-toast'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.brand-logo-styles'))
            ->navigationGroups([
                NavigationGroup::make()->label('Manajemen Kegiatan'),
                NavigationGroup::make()->label('Administrasi Surat'),
                NavigationGroup::make()->label('Layanan Publik'),
                NavigationGroup::make()->label('Pengaturan'),
                NavigationGroup::make()->label('Log'),
                NavigationGroup::make()->label('Laporan'),
                NavigationGroup::make()->label('Halaman Publik'),
            ])

            // Link-link tambahan di sidebar
            ->navigationItems([
                // Beranda utama website
                NavigationItem::make('Beranda Website')
                    ->url(url('/'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-home')
                    ->group('Halaman Publik')
                    ->sort(90),

                // Dashboard agenda publik
                NavigationItem::make('Agenda Publik')
                    ->url(url('/agenda-kegiatan'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-globe-alt')
                    ->group('Halaman Publik')
                    ->sort(100),

            ])

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
                EnsureRoleHasPageAccess::class,
            ]);
    }
}
