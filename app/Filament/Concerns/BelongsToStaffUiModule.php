<?php

namespace App\Filament\Concerns;

use App\Http\Middleware\StaffUiMaintenance;
use Filament\Panel;
use Illuminate\Support\Arr;

trait BelongsToStaffUiModule
{
    abstract protected static function staffUiModule(): string;

    /**
     * @return string | array<string>
     */
    public static function getRouteMiddleware(Panel $panel): string|array
    {
        return [
            ...Arr::wrap(parent::getRouteMiddleware($panel)),
            StaffUiMaintenance::class.':'.static::staffUiModule(),
        ];
    }
}
