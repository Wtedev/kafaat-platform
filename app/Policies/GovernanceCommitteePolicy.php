<?php

namespace App\Policies;

use App\Models\GovernanceCommittee;
use App\Models\User;

class GovernanceCommitteePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function view(User $user, GovernanceCommittee $committee): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function update(User $user, GovernanceCommittee $committee): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function delete(User $user, GovernanceCommittee $committee): bool
    {
        return $user->hasPermission('manage_governance');
    }
}
