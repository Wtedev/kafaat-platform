<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Http\Controllers\StaffUi\StaffDashboardController;
use App\Support\StaffUi\StaffUiModule;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

class Dashboard extends BaseDashboard
{
    use BelongsToStaffUiModule;

    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        $middleware = static::getRouteMiddleware($panel);

        if ($configuration) {
            $middleware = [
                ...$middleware,
                "page-configuration:{$configuration->getKey()}",
            ];
        }

        Route::get(static::getRoutePath($panel), StaffDashboardController::class)
            ->middleware($middleware)
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel));
    }

    protected static function staffUiModule(): string
    {
        return StaffUiModule::SHELL;
    }

    public static function isDiscovered(): bool
    {
        return false;
    }
}
