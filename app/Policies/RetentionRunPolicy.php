<?php

namespace App\Policies;

use App\Models\RetentionRun;
use App\Models\User;

class RetentionRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('retention_runs.view');
    }

    public function view(User $user, RetentionRun $run): bool
    {
        return $user->can('retention_runs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RetentionRun $run): bool
    {
        return false;
    }

    public function delete(User $user, RetentionRun $run): bool
    {
        return false;
    }
}
