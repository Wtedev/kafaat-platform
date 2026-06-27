<?php

namespace App\Policies;

use App\Models\RetentionException;
use App\Models\User;

class RetentionExceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('retention_exceptions.manage');
    }

    public function view(User $user, RetentionException $exception): bool
    {
        return $user->can('retention_exceptions.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('retention_exceptions.manage');
    }

    public function update(User $user, RetentionException $exception): bool
    {
        return $user->can('retention_exceptions.manage');
    }

    public function delete(User $user, RetentionException $exception): bool
    {
        return false;
    }
}
