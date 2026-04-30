<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserCourseProgress;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserCourseProgressPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('progress.view');
    }

    public function view(User $user, UserCourseProgress $progress): bool
    {
        // The learner can always view their own progress
        if ($user->id === $progress->user_id) {
            return true;
        }

        return $user->hasPermissionTo('progress.view');
    }

    public function update(User $user, UserCourseProgress $progress): bool
    {
        return $user->hasPermissionTo('progress.update');
    }
}
