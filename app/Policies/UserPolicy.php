<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * تعديل أدوار Spatie ونوع الحساب من لوحة المستخدمين (صلاحية manage_roles).
     */
    public function updateRole(User $user, ?User $model = null): bool
    {
        return $user->can('manage_roles');
    }

    /**
     * حذف مستخدم — ممنوع لحسابات مدير النظام.
     */
    public function delete(User $actor, User $target): bool
    {
        if (! $actor->can('users.delete')) {
            return false;
        }

        if ($target->isProtectedAdminUser()) {
            return false;
        }

        return true;
    }
}
