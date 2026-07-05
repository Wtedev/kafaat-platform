<?php

namespace App\Policies;

use App\Models\InvestmentDecisionYear;
use App\Models\User;

class InvestmentDecisionYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function view(User $user, InvestmentDecisionYear $year): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function update(User $user, InvestmentDecisionYear $year): bool
    {
        return $user->hasPermission('manage_governance');
    }

    public function delete(User $user, InvestmentDecisionYear $year): bool
    {
        return $user->hasPermission('manage_governance');
    }
}
