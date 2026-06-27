<?php

namespace App\Data\Privacy\Export;

use App\Models\Profile;
use App\Models\User;

final readonly class ProfileExportData
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forUser(User $user): ?array
    {
        $profile = $user->profile;
        if (! $profile instanceof Profile) {
            return null;
        }

        return [
            'gender' => $profile->gender,
            'birth_date' => $profile->birth_date?->toDateString(),
            'city' => $profile->city,
            'job_title' => $profile->job_title,
            'bio' => $profile->bio,
            'membership_type' => $profile->membership_type?->value,
            'membership_badges' => $profile->membership_badges,
            'competency_levels' => $profile->competency_levels,
            'cv_sections' => self::sanitizeCvSections($profile->cv_sections),
            'cv_sections_visibility' => $profile->cv_sections_visibility,
            'cv_language' => $profile->cv_language,
            'iconic_skill' => $profile->iconic_skill,
        ];
    }

    /**
     * @param  mixed  $sections
     * @return array<string, mixed>|null
     */
    private static function sanitizeCvSections(mixed $sections): ?array
    {
        if (! is_array($sections)) {
            return null;
        }

        $forbidden = ['path', 'disk', 'url', 'password', 'token'];

        return self::stripKeysRecursive($sections, $forbidden);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $forbidden
     * @return array<string, mixed>
     */
    private static function stripKeysRecursive(array $data, array $forbidden): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array((string) $key, $forbidden, true)) {
                continue;
            }
            $result[$key] = is_array($value)
                ? self::stripKeysRecursive($value, $forbidden)
                : $value;
        }

        return $result;
    }
}
