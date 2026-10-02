<?php

namespace App\Services\Rbac;

use App\Models\User;

final class StaffNavigationAuthorization
{
    /**
     * @param  list<string>  $permissions
     */
    public static function allowsAll(?User $user, array $permissions): bool
    {
        if ($user === null || $permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
