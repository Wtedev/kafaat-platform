<?php

namespace App\Policies;

use App\Enums\PathStatus;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LearningPathPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('paths.view');
    }

    public function view(User $user, LearningPath $path): bool
    {
        // Published paths are visible to everyone authenticated
        if ($path->status === PathStatus::Published) {
            return true;
        }

        return $user->hasPermissionTo('paths.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('paths.create');
    }

    public function update(User $user, LearningPath $path): bool
    {
        return $user->hasPermissionTo('paths.update');
    }

    public function delete(User $user, LearningPath $path): bool
    {
        return $user->hasPermissionTo('paths.delete');
    }

    public function publish(User $user, LearningPath $path): bool
    {
        return $user->hasPermissionTo('paths.publish');
    }

    public function archive(User $user, LearningPath $path): bool
    {
        return $user->hasPermissionTo('paths.archive');
    }
}
