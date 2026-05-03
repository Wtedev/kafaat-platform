<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Support\FilamentAssignmentVisibility;
use App\Support\StaffFilamentRoles;
use Illuminate\Auth\Access\HandlesAuthorization;

class VolunteerRegistrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('registrations.view');
    }

    public function view(User $user, VolunteerRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return true;
        }

        if (! $user->hasPermissionTo('registrations.view')) {
            return false;
        }

        if ($user->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR)) {
            return false;
        }

        if (FilamentAssignmentVisibility::bypasses($user)) {
            return true;
        }

        if ($user->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR) || StaffFilamentRoles::isProgramsActivitiesManager($user)) {
            $registration->loadMissing('opportunity');

            return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $registration->opportunity);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, VolunteerRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.approve')) {
            return false;
        }

        $registration->loadMissing('opportunity');

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $registration->opportunity);
    }

    public function reject(User $user, VolunteerRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.reject')) {
            return false;
        }

        $registration->loadMissing('opportunity');

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $registration->opportunity);
    }

    public function cancel(User $user, VolunteerRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return in_array($registration->status, [
                RegistrationStatus::Pending,
                RegistrationStatus::Approved,
            ], true);
        }

        if (! $user->hasPermissionTo('registrations.view')) {
            return false;
        }

        $registration->loadMissing('opportunity');

        return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $registration->opportunity);
    }
}
