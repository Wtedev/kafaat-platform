<?php

namespace App\Policies;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingProgramPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('programs.view');
    }

    public function view(User $user, TrainingProgram $program): bool
    {
        if ($program->status === ProgramStatus::Published) {
            return true;
        }

        if (! $user->hasPermissionTo('programs.view')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $program);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('programs.create');
    }

    public function update(User $user, TrainingProgram $program): bool
    {
        if (! $user->hasPermissionTo('programs.update')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $program);
    }

    public function delete(User $user, TrainingProgram $program): bool
    {
        if (! $user->hasPermissionTo('programs.delete')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $program);
    }

    public function publish(User $user, TrainingProgram $program): bool
    {
        if (! $user->hasPermissionTo('programs.publish')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $program);
    }

    public function archive(User $user, TrainingProgram $program): bool
    {
        if (! $user->hasPermissionTo('programs.archive')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $program);
    }
}
