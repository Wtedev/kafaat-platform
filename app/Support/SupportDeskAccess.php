<?php

namespace App\Support;

use App\Models\User;

/**
 * Support tickets are handled only by the accounts listed in config/support_desk.php.
 * Roles and permissions (admin included) do not open the support desk.
 */
final class SupportDeskAccess
{
    public static function isAgent(?User $user): bool
    {
        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        return in_array(strtolower(trim((string) $user->email)), (array) config('support_desk.agents', []), true);
    }
}
