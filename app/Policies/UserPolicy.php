<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function updateRole(User $user, ?User $model = null): bool
    {
        return $user->can('permissions.assign') || $user->can('manage_roles');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('users.view') || $user->can('beneficiaries.view_basic');
    }

    public function view(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true;
        }

        return $user->can('beneficiaries.view_basic') || $user->can('users.view');
    }

    public function viewContact(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true;
        }

        return $user->can('beneficiaries.view_contact');
    }

    public function viewMaskedIdentity(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true;
        }

        return $user->can('beneficiaries.identity.view_masked')
            || $user->can('beneficiaries.identity.view_full');
    }

    public function viewFullIdentity(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        return $user->can('beneficiaries.identity.view_full');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('beneficiaries.update_basic') || $user->can('users.update');
    }

    public function updateSensitive(User $user, User $target): bool
    {
        return $user->can('beneficiaries.update_sensitive') || $user->can('users.update');
    }

    public function downloadCv(User $user, User $target): bool
    {
        return $user->can('beneficiary.cv.download') || $user->can('candidate_pool.cv.download');
    }

    public function viewCv(User $user, User $target): bool
    {
        return $user->can('beneficiary.cv.view')
            || $this->downloadCv($user, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    public function deactivate(User $actor, User $target): bool
    {
        if ($target->isProtectedAdminUser()) {
            return false;
        }

        return $actor->can('beneficiaries.deactivate') || $actor->can('users.activate');
    }
}
