<?php

namespace App\Policies;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Models\User;
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
        // Published programs are visible to all authenticated users
        if ($program->status === ProgramStatus::Published) {
            return true;
        }

        return $user->hasPermissionTo('programs.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('programs.create');
    }

    public function update(User $user, TrainingProgram $program): bool
    {
        return $user->hasPermissionTo('programs.update');
    }

    public function delete(User $user, TrainingProgram $program): bool
    {
        return $user->hasPermissionTo('programs.delete');
    }

    public function publish(User $user, TrainingProgram $program): bool
    {
        return $user->hasPermissionTo('programs.publish');
    }

    public function archive(User $user, TrainingProgram $program): bool
    {
        return $user->hasPermissionTo('programs.archive');
    }
}
