<?php

namespace App\Support;

use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authorization helpers for TrainingProgram and LearningPath policies.
 * Server-side source of truth; not tied to Filament UI.
 */
final class TrainingEntityAuthorization
{
    public static function adminBypass(User $user): bool
    {
        return FilamentAssignmentVisibility::bypasses($user);
    }

    public static function isActive(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public static function isOwnerOfProgram(User $user, TrainingProgram $program): bool
    {
        return $program->owner_id !== null
            && (int) $program->owner_id === (int) $user->id;
    }

    public static function isCreatorOfProgram(User $user, TrainingProgram $program): bool
    {
        return $program->created_by !== null
            && (int) $program->created_by === (int) $user->id;
    }

    public static function isEditorOfProgram(User $user, TrainingProgram $program): bool
    {
        return $program->editors()->whereKey($user->id)->exists();
    }

    public static function isOwnerOfPath(User $user, LearningPath $path): bool
    {
        return $path->owner_id !== null
            && (int) $path->owner_id === (int) $user->id;
    }

    public static function isCreatorOfPath(User $user, LearningPath $path): bool
    {
        return $path->created_by !== null
            && (int) $path->created_by === (int) $user->id;
    }

    public static function isEditorOfPath(User $user, LearningPath $path): bool
    {
        return $path->editors()->whereKey($user->id)->exists();
    }

    /**
     * Active user who is owner, creator, or listed editor (pivot).
     */
    public static function hasActiveProgramStakeholderRole(User $user, TrainingProgram $program): bool
    {
        if (! self::isActive($user)) {
            return false;
        }

        return self::isOwnerOfProgram($user, $program)
            || self::isCreatorOfProgram($user, $program)
            || self::isEditorOfProgram($user, $program);
    }

    /**
     * Active user who is owner, creator, or listed editor (pivot).
     */
    public static function hasActivePathStakeholderRole(User $user, LearningPath $path): bool
    {
        if (! self::isActive($user)) {
            return false;
        }

        return self::isOwnerOfPath($user, $path)
            || self::isCreatorOfPath($user, $path)
            || self::isEditorOfPath($user, $path);
    }

    /**
     * For viewOperational: admin bypass, or any active stakeholder (owner / creator / editor).
     */
    public static function canViewOperationalProgram(User $user, TrainingProgram $program): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        return self::hasActiveProgramStakeholderRole($user, $program);
    }

    public static function canViewOperationalPath(User $user, LearningPath $path): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        return self::hasActivePathStakeholderRole($user, $path);
    }

    /**
     * update on program: admin bypass, or programs.update + active stakeholder.
     */
    public static function canUpdateProgram(User $user, TrainingProgram $program): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        if (! $user->hasPermissionTo('programs.update')) {
            return false;
        }

        return self::hasActiveProgramStakeholderRole($user, $program);
    }

    /**
     * update on path: admin bypass, or paths.update + active stakeholder.
     */
    public static function canUpdatePath(User $user, LearningPath $path): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        if (! $user->hasPermissionTo('paths.update')) {
            return false;
        }

        return self::hasActivePathStakeholderRole($user, $path);
    }

    /**
     * Container structure (attach/detach/reorder programs): same gate as path update for now.
     */
    public static function canUpdatePathContainerStructure(User $user, LearningPath $path): bool
    {
        return self::canUpdatePath($user, $path);
    }

    /**
     * manageEditors: admin bypass, or active owner (not only editor).
     */
    public static function canManageProgramEditors(User $user, TrainingProgram $program): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        return self::isActive($user) && self::isOwnerOfProgram($user, $program);
    }

    public static function canManagePathEditors(User $user, LearningPath $path): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        return self::isActive($user) && self::isOwnerOfPath($user, $path);
    }

    public static function canTransferProgramOwnership(User $user): bool
    {
        return self::adminBypass($user);
    }

    public static function canTransferPathOwnership(User $user): bool
    {
        return self::adminBypass($user);
    }

    /**
     * Restrict a TrainingProgram query to programs where the viewer is an active operational stakeholder.
     * Do not use when adminBypass applies — callers should skip the constraint entirely in that case.
     */
    public static function constrainQueryToOperationalProgramsForViewer(Builder $query, User $viewer): void
    {
        if (! self::isActive($viewer)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();
        $query->where(function (Builder $q) use ($viewer, $table): void {
            $q->where($table.'.owner_id', $viewer->id)
                ->orWhere($table.'.created_by', $viewer->id)
                ->orWhereHas('editors', fn ($eq) => $eq->whereKey($viewer->id));
        });
    }

    /**
     * Restrict a LearningPath query to paths where the viewer is an active operational stakeholder.
     */
    public static function constrainQueryToOperationalPathsForViewer(Builder $query, User $viewer): void
    {
        if (! self::isActive($viewer)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();
        $query->where(function (Builder $q) use ($viewer, $table): void {
            $q->where($table.'.owner_id', $viewer->id)
                ->orWhere($table.'.created_by', $viewer->id)
                ->orWhereHas('editors', fn ($eq) => $eq->whereKey($viewer->id));
        });
    }
}
