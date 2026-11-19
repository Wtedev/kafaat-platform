<?php

namespace App\Policies;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Exports\ProgramRegistrationExportAuthorization;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingProgramPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->checkPermissionTo('programs.view');
    }

    /**
     * Basic record visibility for users who may access the programs tab (listing / non-operational detail).
     */
    public function view(User $user, TrainingProgram $program): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->checkPermissionTo('programs.view');
    }

    /**
     * Registrants, attendance, stats, evaluations — operational surfaces.
     */
    public function viewOperational(User $user, TrainingProgram $program): bool
    {
        return TrainingEntityAuthorization::canViewOperationalProgram($user, $program);
    }

    /**
     * Export program registrants to Excel — requires exports.training (not registrations.view).
     */
    public function exportRegistrants(User $user, TrainingProgram $program): bool
    {
        return ProgramRegistrationExportAuthorization::canExport($user, $program);
    }

    public function create(User $user): bool
    {
        if (! TrainingEntityAuthorization::isActive($user) && ! TrainingEntityAuthorization::adminBypass($user)) {
            return false;
        }

        return TrainingEntityAuthorization::adminBypass($user)
            || $user->checkPermissionTo('programs.create');
    }

    public function update(User $user, TrainingProgram $program): bool
    {
        return TrainingEntityAuthorization::canUpdateProgram($user, $program);
    }

    /**
     * Pivot editors; creator cannot be stripped via policy here — enforced when UI exists.
     */
    public function manageEditors(User $user, TrainingProgram $program): bool
    {
        return TrainingEntityAuthorization::canManageProgramEditors($user, $program);
    }

    /**
     * Admin-only (bypass) ownership transfer.
     */
    public function transferOwnership(User $user, TrainingProgram $program): bool
    {
        return TrainingEntityAuthorization::adminBypass($user);
    }

    public function delete(User $user, TrainingProgram $program): bool
    {
        return $this->canPerformWithProgramPermission($user, $program, 'programs.delete');
    }

    /** Deleted programs can be brought back by whoever may delete them. */
    public function restore(User $user, TrainingProgram $program): bool
    {
        return $this->delete($user, $program);
    }

    /** Programs are never removed for good from the app. */
    public function forceDelete(User $user, TrainingProgram $program): bool
    {
        return false;
    }

    public function viewTrash(User $user): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        return $user->checkPermissionTo('programs.delete') && TrainingEntityAuthorization::isActiveProgramStaff($user);
    }

    /** Copying makes a new draft, so it follows the right to create programs. */
    public function duplicate(User $user, TrainingProgram $program): bool
    {
        return $user->can('create', TrainingProgram::class) && $user->can('view', $program);
    }

    public function publish(User $user, TrainingProgram $program): bool
    {
        return $this->canPerformWithProgramPermission($user, $program, 'programs.publish');
    }

    public function archive(User $user, TrainingProgram $program): bool
    {
        return $this->canPerformWithProgramPermission($user, $program, 'programs.archive');
    }

    private function canPerformWithProgramPermission(User $user, TrainingProgram $program, string $permission): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        if (! $user->checkPermissionTo($permission)) {
            return false;
        }

        return TrainingEntityAuthorization::isActiveProgramStaff($user);
    }
}
