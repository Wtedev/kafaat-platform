<?php

namespace App\Policies;

use App\Models\SecurityLog;
use App\Models\User;

class SecurityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('security_logs.view');
    }

    public function view(User $user, SecurityLog $securityLog): bool
    {
        return $user->can('security_logs.view');
    }

    public function viewSensitiveMetadata(User $user): bool
    {
        return $user->can('security_logs.view_sensitive_metadata');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SecurityLog $securityLog): bool
    {
        return false;
    }

    public function delete(User $user, SecurityLog $securityLog): bool
    {
        return false;
    }
}
