<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Resolves the "main" frontend URL for users leaving the Filament admin panel.
 */
final class MainSiteDashboardUrl
{
    public static function resolve(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return route('home');
        }

        if ($user->isBeneficiary()) {
            return Route::has('portal.dashboard')
                ? route('portal.dashboard')
                : route('home');
        }

        if ($user->isAdmin()) {
            return route('home');
        }

        // Staff and any other authenticated panel user: public site as main entry.
        return route('home');
    }
}
