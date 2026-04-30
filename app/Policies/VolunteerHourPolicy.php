<?php

namespace App\Policies;

use App\Enums\VolunteerHoursStatus;
use App\Models\User;
use App\Models\VolunteerHour;
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

        return $user->hasPermissionTo('volunteer_hours.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('volunteer_hours.create');
    }

    public function approve(User $user, VolunteerHour $volunteerHour): bool
    {
        return $volunteerHour->status === VolunteerHoursStatus::Pending
            && $user->hasPermissionTo('volunteer_hours.approve');
    }

    public function reject(User $user, VolunteerHour $volunteerHour): bool
    {
        return $volunteerHour->status === VolunteerHoursStatus::Pending
            && $user->hasPermissionTo('volunteer_hours.reject');
    }
}
