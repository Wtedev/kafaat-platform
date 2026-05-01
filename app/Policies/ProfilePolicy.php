<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view') || $user->can('edit_profile_badges');
    }

    public function view(User $user, Profile $profile): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function update(User $user, Profile $profile): bool
    {
        return $user->can('roles.view') || $user->can('edit_profile_badges');
    }

    public function delete(User $user, Profile $profile): bool
    {
        return $user->can('roles.view');
    }
}
