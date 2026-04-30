<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\User;
use App\Models\VolunteerRegistration;
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

        return $user->hasPermissionTo('registrations.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, VolunteerRegistration $registration): bool
    {
        return $registration->status === RegistrationStatus::Pending
            && $user->hasPermissionTo('registrations.approve');
    }

    public function reject(User $user, VolunteerRegistration $registration): bool
    {
        return $registration->status === RegistrationStatus::Pending
            && $user->hasPermissionTo('registrations.reject');
    }

    public function cancel(User $user, VolunteerRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return in_array($registration->status, [
                RegistrationStatus::Pending,
                RegistrationStatus::Approved,
            ]);
        }

        return $user->hasPermissionTo('registrations.view');
    }
}
