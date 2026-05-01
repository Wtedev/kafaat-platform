<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolunteerTeam;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

class VolunteerTeamPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('volunteering.view');
    }

    public function view(User $user, VolunteerTeam $team): bool
    {
        if (! $user->hasPermissionTo('volunteering.view')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerTeam($user, $team);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteering.create');
    }

    public function update(User $user, VolunteerTeam $team): bool
    {
        if (! $user->hasPermissionTo('volunteering.update')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerTeam($user, $team);
    }

    public function delete(User $user, VolunteerTeam $team): bool
    {
        if (! $user->hasPermissionTo('volunteering.delete')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerTeam($user, $team);
    }
}
