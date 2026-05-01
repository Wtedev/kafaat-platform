<?php

namespace App\Services\Portal;

use App\Enums\MembershipType;
use App\Models\User;

final class BeneficiaryMembershipResolver
{
    /**
     * Admin-managed membership on profile. When unset, default is مستفيد (not inferred from registrations).
     */
    public static function resolve(User $user): MembershipType
    {
        $user->loadMissing('profile');
        $type = $user->profile?->membership_type;
        if ($type instanceof MembershipType) {
            return $type;
        }
        if (is_string($type) && $type !== '') {
            return MembershipType::tryFrom($type) ?? MembershipType::Beneficiary;
        }

        return MembershipType::Beneficiary;
    }
}
