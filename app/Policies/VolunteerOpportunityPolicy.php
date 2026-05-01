<?php

namespace App\Policies;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

class VolunteerOpportunityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('volunteering.view');
    }

    public function view(User $user, VolunteerOpportunity $opportunity): bool
    {
        if ($opportunity->status === OpportunityStatus::Published) {
            return true;
        }

        if (! $user->hasPermissionTo('volunteering.view')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $opportunity);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteering.create');
    }

    public function update(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (! $user->hasPermissionTo('volunteering.update')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $opportunity);
    }

    public function delete(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (! $user->hasPermissionTo('volunteering.delete')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $opportunity);
    }

    public function publish(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (! $user->hasPermissionTo('volunteering.publish')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $opportunity);
    }

    public function archive(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (! $user->hasPermissionTo('volunteering.archive')) {
            return false;
        }

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $opportunity);
    }
}
