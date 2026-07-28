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

    public function publish(User $user, TrainingProgram $program): bool
    {
        return $this->canPerformWithProgramPermission($user, $program, 'programs.publish');
    }

    public function archive(User $user, TrainingProgram $program): bool
    {
        return $this->canPerformWithProgramPermission($user, $program, 'programs.archive');
    }

    /**
     * Embedded rule: editing program content uses this policy only — not LearningPath container rights.
     */
    private function canPerformWithProgramPermission(User $user, TrainingProgram $program, string $permission): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        if (! $user->checkPermissionTo($permission)) {
            return false;
        }

        return TrainingEntityAuthorization::hasActiveProgramStakeholderRole($user, $program);
    }
}
