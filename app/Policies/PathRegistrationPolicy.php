<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\PathRegistration;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PathRegistrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('registrations.view');
    }

    public function view(User $user, PathRegistration $registration): bool
    {
        // The registrant can always see their own registration
        if ($user->id === $registration->user_id) {
            return true;
        }

        return $user->hasPermissionTo('registrations.view');
    }

    public function create(User $user): bool
    {
        // Any authenticated user may request registration
        return true;
    }

    public function approve(User $user, PathRegistration $registration): bool
    {
        return $registration->status === RegistrationStatus::Pending
            && $user->hasPermissionTo('registrations.approve');
    }

    public function reject(User $user, PathRegistration $registration): bool
    {
        return $registration->status === RegistrationStatus::Pending
            && $user->hasPermissionTo('registrations.reject');
    }

    public function cancel(User $user, PathRegistration $registration): bool
    {
        // The registrant can cancel their own pending or approved registration
        if ($user->id === $registration->user_id) {
            return in_array($registration->status, [
                RegistrationStatus::Pending,
                RegistrationStatus::Approved,
            ]);
        }

        // Admins/staff with view permission can cancel on behalf of users
        return $user->hasPermissionTo('registrations.view');
    }
}
