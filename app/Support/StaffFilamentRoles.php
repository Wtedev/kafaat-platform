<?php

namespace App\Support;

use App\Models\User;

/**
 * Spatie role names for Filament / assignment visibility (training vs volunteering coordinators).
 */
final class StaffFilamentRoles
{
    /** Roles scoped to assigned training programs (operational coordinator). */
    public const TRAINING_COORDINATOR = [
        'programs_management',
    ];

    /** Roles scoped to assigned volunteer opportunities / teams. */
    public const VOLUNTEERING_COORDINATOR = [
        'volunteer_management',
    ];

    /** Full training + volunteering access (no per-row assignee filter in list scopes). */
    public const CROSS_PROGRAMS_ACTIVITIES = 'training_management';

    public static function isProgramsActivitiesManager(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::CROSS_PROGRAMS_ACTIVITIES);
    }

    public static function hasTrainingCoordinatorRole(User $user): bool
    {
        return $user->hasAnyRole(self::TRAINING_COORDINATOR);
    }

    public static function hasVolunteeringCoordinatorRole(User $user): bool
    {
        return $user->hasAnyRole(self::VOLUNTEERING_COORDINATOR);
    }

    /**
     * @return list<string>
     */
    public static function assignableTrainingCoordinatorRoleNames(): array
    {
        return [
            'programs_management',
            'training_management',
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignableVolunteeringCoordinatorRoleNames(): array
    {
        return [
            'volunteer_management',
            'training_management',
        ];
    }
}
