<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\FilamentAssignmentVisibility;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramRegistrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('registrations.view');
    }

    public function view(User $user, ProgramRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return true;
        }

        if (! $user->hasPermissionTo('registrations.view')) {
            return false;
        }

        if ($user->hasRole('volunteering_manager')) {
            return false;
        }

        if (FilamentAssignmentVisibility::bypasses($user)) {
            return true;
        }

        $registration->loadMissing('trainingProgram');

        return $registration->trainingProgram !== null
            && $user->can('viewOperational', $registration->trainingProgram);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProgramRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return false;
        }

        return $this->view($user, $registration);
    }

    public function delete(User $user, ProgramRegistration $registration): bool
    {
        if ($user->id === $registration->user_id) {
            return false;
        }

        return $this->view($user, $registration);
    }

    public function approve(User $user, ProgramRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.approve')) {
            return false;
        }

        $registration->loadMissing('trainingProgram');

        return $registration->trainingProgram !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->trainingProgram)
            );
    }

    public function reject(User $user, ProgramRegistration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Pending || ! $user->hasPermissionTo('registrations.reject')) {
            return false;
        }

        $registration->loadMissing('trainingProgram');

        return $registration->trainingProgram !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->trainingProgram)
            );
    }

    public function cancel(User $user, ProgramRegistration $registration): bool
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

        $registration->loadMissing('trainingProgram');

        return $registration->trainingProgram !== null
            && (
                TrainingEntityAuthorization::adminBypass($user)
                || $user->can('viewOperational', $registration->trainingProgram)
            );
    }
}
