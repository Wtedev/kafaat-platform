<?php

namespace App\Support\Auth;

/**
 * Canonical auth email normalization: trim + lowercase.
 *
 * Passwords must never be passed through this helper.
 */
final class EmailNormalizer
{
    public static function normalize(?string $email): string
    {
        if ($email === null) {
            return '';
        }

        return strtolower(trim($email));
    }

    public static function equals(?string $left, ?string $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }
}
