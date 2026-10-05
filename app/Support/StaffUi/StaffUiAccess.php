<?php

namespace App\Support\StaffUi;

use App\Models\User;

final class StaffUiAccess
{
    public static function seesNewUi(?User $user): bool
    {
        return $user instanceof User && ($user->isAdmin() || $user->hasRole('super_admin'));
    }

    public static function moduleIsReady(?string $module): bool
    {
        if (! is_string($module) || $module === '') {
            return false;
        }

        $ready = config('staff_ui.ready_modules', []);

        return is_array($ready) && in_array($module, $ready, true);
    }

    public static function invitesEnabled(): bool
    {
        return (bool) config('staff_ui.invites_enabled');
    }
}
