<?php

namespace App\Policies;

use App\Models\GovernanceDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GovernanceDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function view(User $user, GovernanceDocument $document): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function update(User $user, GovernanceDocument $document): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function delete(User $user, GovernanceDocument $document): bool
    {
        return $user->hasPermission('manage_governance');
    }
}
