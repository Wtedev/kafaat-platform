<?php

namespace App\Support;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerTeam;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament visibility helpers. Volunteer entities still use assigned_to for list scoping.
 *
 * Training programs: access control is owner/editor/admin (see TrainingProgramPolicy / TrainingEntityAuthorization).
 * `assigned_to` on TrainingProgram is retained as an optional **operational coordinator** (منسق), distinct from `owner_id` (المسؤول).
 * `userManagesTrainingProgram()` combines stakeholder access with legacy coordinator assignment for notifications/targeting.
 */
final class FilamentAssignmentVisibility
{
    public static function bypasses(?User $user): bool
    {
        return $user !== null && (
            $user->isAdmin()
            || $user->hasRole('admin')
            || $user->can('manage_roles')
        );
    }

    /**
     * Whether the user may treat this program as a management/targeting context (e.g. in-app notifications to registrants).
     * Uses owner/creator/editor stakeholder rules first; falls back to legacy training_manager + assigned_to coordinator.
     */
    public static function userManagesTrainingProgram(User $user, TrainingProgram $program): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        if (TrainingEntityAuthorization::hasActiveProgramStakeholderRole($user, $program)) {
            return true;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($user)) {
            return true;
        }

        return $user->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR)
            && (int) $program->assigned_to === (int) $user->id;
    }

    public static function userManagesVolunteerOpportunity(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($user)) {
            return true;
        }

        return $user->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)
            && (int) $opportunity->assigned_to === $user->id;
    }

    /**
     * Previously restricted training_manager lists to assigned_to only; that duplicated owner logic and hid programs
     * from tab users who should see the full list per programs.view. Authorization lives in policies; this scope is a no-op.
     *
     * @deprecated Retained so TrainingProgram::forFilamentAssignmentAccess() callers stay stable without row-level filtering here.
     */
    public static function constrainTrainingPrograms(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }
    }

    public static function constrainVolunteerOpportunities(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($viewer)) {
            return;
        }

        if ($viewer->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)) {
            $query->where($query->getModel()->getTable().'.assigned_to', $viewer->id);
        }
    }

    public static function userManagesVolunteerTeam(User $user, VolunteerTeam $team): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($user)) {
            return true;
        }

        return $user->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)
            && (int) $team->assigned_to === $user->id;
    }

    public static function constrainVolunteerTeams(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($viewer)) {
            return;
        }

        if ($viewer->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)) {
            $query->where($query->getModel()->getTable().'.assigned_to', $viewer->id);
        }
    }

    public static function constrainProgramRegistrations(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        $regTable = $query->getModel()->getTable();
        $query->where(function (Builder $q) use ($viewer, $regTable): void {
            $q->where($regTable.'.user_id', $viewer->id)
                ->orWhereHas('trainingProgram', function (Builder $programQuery) use ($viewer): void {
                    TrainingEntityAuthorization::constrainQueryToOperationalProgramsForViewer($programQuery, $viewer);
                });
        });
    }

    /**
     * Path registrations with learner PII: own row, or operational stakeholder on the parent path.
     */
    public static function constrainPathRegistrations(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        $regTable = $query->getModel()->getTable();
        $query->where(function (Builder $q) use ($viewer, $regTable): void {
            $q->where($regTable.'.user_id', $viewer->id)
                ->orWhereHas('learningPath', function (Builder $pathQuery) use ($viewer): void {
                    TrainingEntityAuthorization::constrainQueryToOperationalPathsForViewer($pathQuery, $viewer);
                });
        });
    }

    public static function constrainVolunteerRegistrations(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($viewer)) {
            return;
        }

        if ($viewer->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR)) {
            $query->whereHas('opportunity', function (Builder $q) use ($viewer): void {
                $q->where('assigned_to', $viewer->id);
            });
        }
    }
}
