<?php

namespace App\Support;

use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Authorization helpers for TrainingProgram policies.
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

    /**
     * Every active staff member works on every program; owner, creator and editors no longer
     * limit access. What a staff member may do is decided by their permissions alone.
     */
    public static function isActiveProgramStaff(User $user): bool
    {
        return self::isActive($user) && $user->isAdminOrStaff();
    }

    /**
     * For viewOperational: admin bypass, or any active staff member.
     */
    public static function canViewOperationalProgram(User $user, TrainingProgram $program): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        return self::isActiveProgramStaff($user);
    }

    /**
     * update on program: admin bypass, or programs.update for any active staff member.
     */
    public static function canUpdateProgram(User $user, TrainingProgram $program): bool
    {
        if (self::adminBypass($user)) {
            return true;
        }

        if (! $user->checkPermissionTo('programs.update')) {
            return false;
        }

        return self::isActiveProgramStaff($user);
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

    public static function canTransferProgramOwnership(User $user): bool
    {
        return self::adminBypass($user);
    }

    /**
     * Restrict a TrainingProgram query to what the viewer may operate on: every program for active staff, nothing otherwise.
     * Do not use when adminBypass applies — callers should skip the constraint entirely in that case.
     */
    public static function constrainQueryToOperationalProgramsForViewer(Builder $query, User $viewer): void
    {
        if (! self::isActiveProgramStaff($viewer)) {
            $query->whereRaw('1 = 0');
        }
    }
}
