<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\User;

/**
 * اكتمال بيانات صفحة الكفاءة اللازمة لإصدار الشهادات واستكمال الملف المهني.
 */
final class CompetencyDataCompleteness
{
    /**
     * @return array{
     *     complete: bool,
     *     percent: int,
     *     filled: int,
     *     total: int,
     *     missing: list<array{key: string, label: string}>,
     *     waiting_label: string
     * }
     */
    public static function forUser(User $user): array
    {
        $user->loadMissing('profile');
        $profile = $user->profile;

        $checks = [
            [
                'key' => 'job_title',
                'label' => 'المسمى الوظيفي',
                'ok' => filled(trim((string) ($profile?->job_title ?? ''))),
            ],
            [
                'key' => 'iconic_skill',
                'label' => 'المهارة الأيقونية',
                'ok' => filled(trim((string) ($profile?->iconic_skill ?? ''))),
            ],
            [
                'key' => 'bio',
                'label' => 'نبذة تعريفية',
                'ok' => filled(trim((string) ($profile?->bio ?? ''))),
            ],
            [
                'key' => 'skills',
                'label' => 'المهارات',
                'ok' => self::hasNamedSkills($profile),
            ],
            [
                'key' => 'education_or_experience',
                'label' => 'التعليم أو الخبرات',
                'ok' => self::hasEducation($profile) || self::hasExperience($profile),
            ],
        ];

        $total = count($checks);
        $filled = count(array_filter($checks, fn (array $c): bool => $c['ok']));
        $missing = [];
        foreach ($checks as $check) {
            if (! $check['ok']) {
                $missing[] = [
                    'key' => $check['key'],
                    'label' => $check['label'],
                ];
            }
        }

        $percent = $total > 0 ? (int) round(($filled / $total) * 100) : 100;

        return [
            'complete' => $missing === [],
            'percent' => $percent,
            'filled' => $filled,
            'total' => $total,
            'missing' => $missing,
            'waiting_label' => 'بانتظار إكمال بياناتك',
        ];
    }

    private static function hasNamedSkills(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        foreach ($profile->cvSkillsStructured() as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (filled(trim((string) ($row['skill_name'] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    private static function hasEducation(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        foreach ($profile->cvEducationStructured() as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (filled(trim((string) ($row['institution'] ?? ''))) || filled(trim((string) ($row['degree_or_program'] ?? '')))) {
                return true;
            }
        }

        return false;
    }

    private static function hasExperience(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        foreach ($profile->cvExperienceStructured() as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (filled(trim((string) ($row['title'] ?? ''))) || filled(trim((string) ($row['organization'] ?? '')))) {
                return true;
            }
        }

        return false;
    }
}
