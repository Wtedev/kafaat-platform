<?php

namespace App\Services\Volunteering;

use App\Models\User;
use App\Models\VolunteerTeam;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;

final class VolunteerTeamStaffAccess
{
    /**
     * @param  Builder<VolunteerTeam>  $query
     * @return Builder<VolunteerTeam>
     */
    public static function constrain(Builder $query, ?User $viewer): Builder
    {
        $query->forFilamentAssignmentAccess($viewer);

        return $query;
    }

    public static function canCreate(?User $user): bool
    {
        return $user !== null
            && FilamentAssignmentVisibility::bypasses($user)
            && VolunteerTeam::canonical() === null;
    }
}
