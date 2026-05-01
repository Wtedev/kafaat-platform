<?php

namespace App\Services\Rbac;

use App\Models\User;

/**
 * Thin RBAC facade over Spatie — keeps app code decoupled from the package API.
 */
final class RbacService
{
    public function hasPermission(User $user, string $permission, ?string $guardName = null): bool
    {
        $guard = $guardName ?? RbacCatalog::GUARD_WEB;

        return $user->hasPermissionTo($permission, $guard);
    }

    /**
     * @param  string|array<int, string|\BackedEnum>  $roles
     */
    public function hasRole(User $user, string|array $roles, ?string $guardName = null): bool
    {
        $guard = $guardName ?? RbacCatalog::GUARD_WEB;

        return $user->hasRole($roles, $guard);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(User $user, array $roles, ?string $guardName = null): bool
    {
        $guard = $guardName ?? RbacCatalog::GUARD_WEB;

        return $user->hasAnyRole($roles, $guard);
    }
}
