<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\LatestInAppNotificationsWidget;
use App\Filament\Widgets\PlatformStatsWidget;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use App\Http\Middleware\EnsureOtpVerified;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            function (): string {
                if (filament()->getId() !== 'admin') {
                    return '';
                }

                return view('filament.components.admin-main-site-button')->render();
            },
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            function (): string {
                if (filament()->getId() !== 'admin') {
                    return '';
                }

                return view('filament.components.admin-notifications-bell')->render();
            },
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): string {
                if (! filament()->getCurrentPanel() || filament()->getId() !== 'admin') {
                    return '';
                }

                $fonts = asset('css/shamel-fonts.css');
                $href = asset('css/filament-admin-surface.css').'?v=31';

                return '<link rel="stylesheet" href="'.e($fonts).'"><link rel="stylesheet" href="'.e($href).'">';
            },
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            function (): string {
                if (! filament()->getCurrentPanel() || filament()->getId() !== 'admin') {
                    return '';
                }

                $calendar = asset('js/filament/training-schedule-calendar.js').'?v=1';

                return '<script src="'.e($calendar).'"></script>';
            },
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                if (! filament()->getCurrentPanel() || filament()->getId() !== 'admin') {
                    return '';
                }

                $user = auth()->user();
                if ($user === null || $user->notification_prefs_set_at !== null) {
                    return '';
                }

                return view('partials.notification-prefs-modal')->render();            },
        );

    }

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('كفاءات')
            ->brandLogo(asset('images/brand/kafaat-logo-white.svg'))
            ->brandLogoHeight('2.25rem')
            ->bootUsing(fn () => app()->setLocale('ar'))
            ->font('FF Shamel')
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex('#335483'),
                'gray' => Color::Zinc,
                'danger' => Color::hex('#ec6056'),
                'warning' => Color::hex('#fbbb2e'),
                'success' => Color::hex('#1a9399'),
            ])
            ->globalSearch(false)
            ->maxContentWidth(Width::SevenExtraLarge)
            ->spa(false)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PlatformStatsWidget::class,
                LatestInAppNotificationsWidget::class,
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureOtpVerified::class,
            ])
            ->authGuard('web');

        return $panel;
    }
}
