<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\PathRegistration;
use App\Models\User;
use App\Support\TrainingEntityAuthorization;
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
        if ($user->id === $registration->user_id) {
            return true;
        }

        if (! $user->hasPermissionTo('registrations.view')) {
            return false;
        }

        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        $registration->loadMissing('learningPath');

        return $registration->learningPath !== null
            && $user->can('viewOperational', $registration->learningPath);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PathRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return false;
        }

        return $this->view($user, $registration);
    }

    public function delete(User $user, PathRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return false;
        }

        return $this->view($user, $registration);
    }

    public function approve(User $user, PathRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.approve')) {
            return false;
        }

        $registration->loadMissing('learningPath');

        return $registration->learningPath !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->learningPath)
            );
    }

    public function reject(User $user, PathRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.reject')) {
            return false;
        }

        $registration->loadMissing('learningPath');

        return $registration->learningPath !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->learningPath)
            );
    }

    public function cancel(User $user, PathRegistration $registration): bool
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

        $registration->loadMissing('learningPath');

        return $registration->learningPath !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->learningPath)
            );
    }
}
