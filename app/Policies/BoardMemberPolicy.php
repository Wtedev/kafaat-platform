<?php

namespace App\Policies;

use App\Models\BoardMember;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoardMemberPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function view(User $user, BoardMember $member): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function update(User $user, BoardMember $member): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function delete(User $user, BoardMember $member): bool
    {
        return $user->hasPermission('manage_governance');
    }
}
