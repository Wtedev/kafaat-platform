<?php

namespace App\Policies;

use App\Enums\RetentionPolicyStatus;
use App\Models\RetentionPolicy;
use App\Models\User;

class RetentionPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('retention_policies.view');
    }

    public function view(User $user, RetentionPolicy $policy): bool
    {
        return $user->can('retention_policies.view');
    }

    public function create(User $user): bool
    {
        return $user->can('retention_policies.create') || $user->can('retention_policies.manage');
    }

    public function update(User $user, RetentionPolicy $policy): bool
    {
        if ($policy->status !== RetentionPolicyStatus::Draft) {
            return false;
        }

        return $user->can('retention_policies.update_draft') || $user->can('retention_policies.manage');
    }

    public function delete(User $user, RetentionPolicy $policy): bool
    {
        return false;
    }
}
