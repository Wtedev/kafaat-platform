<?php

namespace App\Support;

use App\Models\User;

/**
 * تنسيق ظهور الموظفين في لوحات Filament بعد إلغاء أدوار الأقسام الفرعية.
 * الاعتماد على الصلاحيات المباشرة + علاقات التعيين (assigned_to).
 */
final class StaffFilamentRoles
{
    public const TRAINING_COORDINATOR = ['staff'];

    public const VOLUNTEERING_COORDINATOR = ['staff'];

    public const CROSS_PROGRAMS_ACTIVITIES = 'staff';

    public static function isProgramsActivitiesManager(?User $user): bool
    {
        return $user !== null && (
            $user->isAdmin()
            || $user->can('manage_programs')
            || $user->can('programs.update')
        );
    }

    public static function hasTrainingCoordinatorRole(User $user): bool
    {
        return $user->isAdminOrStaff() && (
            $user->can('programs.view') || $user->can('paths.view') || $user->can('manage_programs')
        );
    }

    public static function hasVolunteeringCoordinatorRole(User $user): bool
    {
        return $user->isAdminOrStaff() && (
            $user->can('volunteering.view') || $user->can('manage_volunteers')
        );
    }

    /** @return list<string> */
    public static function assignableTrainingCoordinatorRoleNames(): array
    {
        return ['staff'];
    }

    /** @return list<string> */
    public static function assignableVolunteeringCoordinatorRoleNames(): array
    {
        return ['staff'];
    }
}
