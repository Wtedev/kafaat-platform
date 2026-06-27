<?php

namespace App\Services\Identity;

use Illuminate\Support\Str;

class PersonNameService
{
    public const MAX_PART_LENGTH = 100;

    /**
     * @return list<string>
     */
    public static function partKeys(): array
    {
        return ['first_name', 'father_name', 'grandfather_name', 'family_name'];
    }

    public static function normalizePart(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strip_tags(trim($value));
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);

        return $clean === '' ? null : $clean;
    }

    public static function isValidPart(?string $value): bool
    {
        $normalized = self::normalizePart($value);

        if ($normalized === null || mb_strlen($normalized) > self::MAX_PART_LENGTH) {
            return false;
        }

        if (preg_match('/\d/u', $normalized)) {
            return false;
        }

        return (bool) preg_match("/^[\p{Arabic}a-zA-Z\s\-']+$/u", $normalized);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function buildFullName(array $parts): string
    {
        $segments = [];

        foreach (self::partKeys() as $key) {
            $segment = self::normalizePart(isset($parts[$key]) ? (string) $parts[$key] : null);
            if ($segment !== null) {
                $segments[] = $segment;
            }
        }

        return implode(' ', $segments);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    public static function hasAllRequiredParts(array $parts): bool
    {
        foreach (self::partKeys() as $key) {
            if (! self::isValidPart(isset($parts[$key]) ? (string) $parts[$key] : null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return array<string, string>
     */
    public static function normalizedParts(array $parts): array
    {
        $out = [];

        foreach (self::partKeys() as $key) {
            $normalized = self::normalizePart(isset($parts[$key]) ? (string) $parts[$key] : null);
            if ($normalized === null) {
                throw new \InvalidArgumentException("Missing or invalid name part: {$key}");
            }
            $out[$key] = $normalized;
        }

        return $out;
    }

    public static function syncCompatibilityName(array &$userAttributes, array $parts): void
    {
        $full = self::buildFullName($parts);

        if ($full !== '') {
            $userAttributes['name'] = Str::limit($full, 255, '');
        }
    }
}
