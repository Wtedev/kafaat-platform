<?php

namespace App\Support;

use App\Models\User;

/**
 * Spatie role names for Filament / assignment visibility (training vs volunteering coordinators).
 * New seed roles map here so policies and scopes stay aligned without duplicating long role lists.
 */
final class StaffFilamentRoles
{
    /** Roles that use legacy "coordinator" scoping via {@see TrainingProgram::$assigned_to}. */
    public const TRAINING_COORDINATOR = [
        'training_manager',
        'training_enablement_manager',
    ];

    /** Roles that use {@see VolunteerOpportunity::$assigned_to} / volunteer team assignee scoping. */
    public const VOLUNTEERING_COORDINATOR = [
        'volunteering_manager',
        'volunteer_manager',
    ];

    /** Full training + volunteering access (no per-row assignee filter in list scopes). */
    public const CROSS_PROGRAMS_ACTIVITIES = 'programs_activities_manager';

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
            'training_manager',
            'training_enablement_manager',
            'programs_activities_manager',
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignableVolunteeringCoordinatorRoleNames(): array
    {
        return [
            'volunteering_manager',
            'volunteer_manager',
            'programs_activities_manager',
        ];
    }
}
