<?php

namespace App\Policies;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
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
        // Published opportunities are visible to all authenticated users
        if ($opportunity->status === OpportunityStatus::Published) {
            return true;
        }

        return $user->hasPermissionTo('volunteering.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteering.create');
    }

    public function update(User $user, VolunteerOpportunity $opportunity): bool
    {
        return $user->hasPermissionTo('volunteering.update');
    }

    public function delete(User $user, VolunteerOpportunity $opportunity): bool
    {
        return $user->hasPermissionTo('volunteering.delete');
    }

    public function publish(User $user, VolunteerOpportunity $opportunity): bool
    {
        return $user->hasPermissionTo('volunteering.publish');
    }

    public function archive(User $user, VolunteerOpportunity $opportunity): bool
    {
        return $user->hasPermissionTo('volunteering.archive');
    }
}
