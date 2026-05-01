<?php

namespace App\Support;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerTeam;
use Illuminate\Database\Eloquent\Builder;

/**
 * منطق موحّد لعرض بيانات لوحة Filament حسب تعيين المسؤول (assigned_to).
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

    public static function userManagesTrainingProgram(User $user, TrainingProgram $program): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        return $user->hasRole('training_manager')
            && (int) $program->assigned_to === $user->id;
    }

    public static function userManagesVolunteerOpportunity(User $user, VolunteerOpportunity $opportunity): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        return $user->hasRole('volunteering_manager')
            && (int) $opportunity->assigned_to === $user->id;
    }

    public static function constrainTrainingPrograms(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if ($viewer->hasRole('training_manager')) {
            $query->where($query->getModel()->getTable().'.assigned_to', $viewer->id);
        }
    }

    public static function constrainVolunteerOpportunities(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if ($viewer->hasRole('volunteering_manager')) {
            $query->where($query->getModel()->getTable().'.assigned_to', $viewer->id);
        }
    }

    public static function userManagesVolunteerTeam(User $user, VolunteerTeam $team): bool
    {
        if (self::bypasses($user)) {
            return true;
        }

        return $user->hasRole('volunteering_manager')
            && (int) $team->assigned_to === $user->id;
    }

    public static function constrainVolunteerTeams(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if ($viewer->hasRole('volunteering_manager')) {
            $query->where($query->getModel()->getTable().'.assigned_to', $viewer->id);
        }
    }

    public static function constrainProgramRegistrations(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if ($viewer->hasRole('training_manager')) {
            $query->whereHas('trainingProgram', function (Builder $q) use ($viewer): void {
                $q->where('assigned_to', $viewer->id);
            });
        }
    }

    public static function constrainVolunteerRegistrations(Builder $query, ?User $viewer): void
    {
        if ($viewer === null || self::bypasses($viewer)) {
            return;
        }

        if ($viewer->hasRole('volunteering_manager')) {
            $query->whereHas('opportunity', function (Builder $q) use ($viewer): void {
                $q->where('assigned_to', $viewer->id);
            });
        }
    }
}
