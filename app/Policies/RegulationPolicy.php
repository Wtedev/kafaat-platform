<?php

namespace App\Policies;

use App\Models\Regulation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegulationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_regulations');
    }

    public function view(User $user, Regulation $regulation): bool
    {
        return $user->hasPermission('manage_regulations');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_regulations');
    }

    public function update(User $user, Regulation $regulation): bool
    {
        return $user->hasPermission('manage_regulations');
    }

    public function delete(User $user, Regulation $regulation): bool
    {
        return $user->hasPermission('manage_regulations');
    }
}
