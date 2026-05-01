<?php

namespace App\Policies;

use App\Enums\VolunteerHoursStatus;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

class VolunteerHourPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('volunteer_hours.view');
    }

    public function view(User $user, VolunteerHour $volunteerHour): bool
    {
        if ($user->id === $volunteerHour->user_id) {
            return true;
        }

        if (! $user->hasPermissionTo('volunteer_hours.view')) {
            return false;
        }

        if (FilamentAssignmentVisibility::bypasses($user)) {
            return true;
        }

        if ($user->hasRole('volunteering_manager')) {
            $volunteerHour->loadMissing('opportunity');

            return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $volunteerHour->opportunity);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteer_hours.create');
    }

    public function approve(User $user, VolunteerHour $volunteerHour): bool
    {
        if ($volunteerHour->status !== VolunteerHoursStatus::Pending || ! $user->hasPermissionTo('volunteer_hours.approve')) {
            return false;
        }

        $volunteerHour->loadMissing('opportunity');

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $volunteerHour->opportunity);
    }

    public function reject(User $user, VolunteerHour $volunteerHour): bool
    {
        if ($volunteerHour->status !== VolunteerHoursStatus::Pending || ! $user->hasPermissionTo('volunteer_hours.reject')) {
            return false;
        }

        $volunteerHour->loadMissing('opportunity');

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $volunteerHour->opportunity);
    }
}
