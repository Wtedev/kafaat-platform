<?php

namespace App\Data\Privacy\Export;

use App\Enums\IdentityType;
use App\Models\User;

final readonly class AccountExportData
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(User $user): array
    {
        return [
            'beneficiary_reference' => (string) $user->id,
            'full_name' => $user->fullName(),
            'email' => $user->email,
            'phone' => $user->phone,
            'birth_date' => $user->profile?->birth_date?->toDateString(),
            'identity_type' => $user->identity_type instanceof IdentityType ? $user->identity_type->value : null,
            'identity_masked' => $user->maskedIdentityNumber(),
            'account_created_at' => $user->created_at?->toIso8601String(),
            'account_status' => $user->account_status?->value,
        ];
    }
}
