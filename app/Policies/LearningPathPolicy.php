<?php

namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class LearningPathPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->hasPermissionTo('paths.view');
    }

    /**
     * Basic path record visibility (tab / listing / non-operational detail).
     */
    public function view(User $user, LearningPath $path): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->hasPermissionTo('paths.view');
    }

    /**
     * Path-level operational data (e.g. registrations on the path).
     */
    public function viewOperational(User $user, LearningPath $path): bool
    {
        return TrainingEntityAuthorization::canViewOperationalPath($user, $path);
    }

    public function create(User $user): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->hasPermissionTo('paths.create');
    }

    public function update(User $user, LearningPath $path): bool
    {
        return TrainingEntityAuthorization::canUpdatePath($user, $path);
    }

    /**
     * Attach / detach / reorder programs in the path — does not grant TrainingProgram::update.
     */
    public function updateContainerStructure(User $user, LearningPath $path): bool
    {
        return TrainingEntityAuthorization::canUpdatePathContainerStructure($user, $path);
    }

    public function manageEditors(User $user, LearningPath $path): bool
    {
        return TrainingEntityAuthorization::canManagePathEditors($user, $path);
    }

    public function transferOwnership(User $user, LearningPath $path): bool
    {
        return TrainingEntityAuthorization::adminBypass($user);
    }

    public function delete(User $user, LearningPath $path): bool
    {
        return $this->canPerformWithPathPermission($user, $path, 'paths.delete');
    }

    public function publish(User $user, LearningPath $path): bool
    {
        return $this->canPerformWithPathPermission($user, $path, 'paths.publish');
    }

    public function archive(User $user, LearningPath $path): bool
    {
        return $this->canPerformWithPathPermission($user, $path, 'paths.archive');
    }

    private function canPerformWithPathPermission(User $user, LearningPath $path, string $permission): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        if (! $user->hasPermissionTo($permission)) {
            return false;
        }

        return TrainingEntityAuthorization::hasActivePathStakeholderRole($user, $path);
    }
}
