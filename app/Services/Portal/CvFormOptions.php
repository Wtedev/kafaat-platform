<?php

namespace App\Services\Portal;

final class CvFormOptions
{
    /** @var list<string> */
    public const SKILL_LEVELS = ['مبتدئ', 'متوسط', 'متقدم', 'خبير'];

    /** @var list<string> */
    public const SKILL_CATEGORIES = ['تقنية', 'شخصية', 'إدارية', 'أخرى'];

    /** @var list<string> */
    public const LANGUAGE_LEVELS = ['مبتدئ', 'متوسط', 'متقدم', 'طليق', 'لغة أم'];

    /** Stored keys (English) — forms & PDF use labels via helpers */
    public const WORK_MODE_KEYS = ['on_site', 'remote', 'hybrid'];

    public const EMPLOYMENT_KEYS = ['full_time', 'part_time', 'internship', 'volunteer', 'project', 'participation'];

    /** @var list<string> */
    public const LINK_TYPES = ['LinkedIn', 'GitHub', 'Portfolio', 'Website', 'Other'];

    /** @var array<string, string> */
    private const LEGACY_WORK_MODE_AR = [
        'حضوري' => 'on_site',
        'عن بُعد' => 'remote',
        'هجين' => 'hybrid',
    ];

    /** @var array<string, string> */
    private const LEGACY_EMPLOYMENT_AR = [
        'دوام كامل' => 'full_time',
        'دوام جزئي' => 'part_time',
        'تدريب' => 'internship',
        'تطوع' => 'volunteer',
        'مشروع' => 'project',
        'مشاركة' => 'participation',
    ];

    public static function normalizeWorkMode(string $value): string
    {
        $v = trim($value);
        if (in_array($v, self::WORK_MODE_KEYS, true)) {
            return $v;
        }

        return self::LEGACY_WORK_MODE_AR[$v] ?? 'on_site';
    }

    public static function normalizeEmployment(string $value): string
    {
        $v = trim($value);
        if (in_array($v, self::EMPLOYMENT_KEYS, true)) {
            return $v;
        }

        return self::LEGACY_EMPLOYMENT_AR[$v] ?? 'participation';
    }

    public static function workModeLabel(string $key, string $locale): string
    {
        $ar = ['on_site' => 'حضوري', 'remote' => 'عن بُعد', 'hybrid' => 'هجين'];
        $en = ['on_site' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];
        $k = self::normalizeWorkMode($key);

        return ($locale === 'en' ? $en : $ar)[$k] ?? $k;
    }

    public static function employmentLabel(string $key, string $locale): string
    {
        $ar = [
            'full_time' => 'دوام كامل',
            'part_time' => 'دوام جزئي',
            'internship' => 'تدريب',
            'volunteer' => 'تطوع',
            'project' => 'مشروع',
            'participation' => 'مشاركة',
        ];
        $en = [
            'full_time' => 'Full-time',
            'part_time' => 'Part-time',
            'internship' => 'Internship',
            'volunteer' => 'Volunteer',
            'project' => 'Project',
            'participation' => 'Participation',
        ];
        $k = self::normalizeEmployment($key);

        return ($locale === 'en' ? $en : $ar)[$k] ?? $k;
    }

    /**
     * @return array<string, string>
     */
    public static function linkTypeLabels(): array
    {
        return [
            'LinkedIn' => 'لينكدإن',
            'GitHub' => 'GitHub',
            'Portfolio' => 'معرض أعمال',
            'Website' => 'موقع',
            'Other' => 'أخرى',
        ];
    }

    public static function languageLevelLabel(string $level, string $locale): string
    {
        if ($locale !== 'en') {
            return $level;
        }

        $map = [
            'مبتدئ' => 'Beginner',
            'متوسط' => 'Intermediate',
            'متقدم' => 'Advanced',
            'طليق' => 'Fluent',
            'لغة أم' => 'Native',
        ];

        return $map[$level] ?? $level;
    }

    public static function skillLevelLabel(string $level, string $locale): string
    {
        if ($locale !== 'en') {
            return $level;
        }

        $map = [
            'مبتدئ' => 'Beginner',
            'متوسط' => 'Intermediate',
            'متقدم' => 'Advanced',
            'خبير' => 'Expert',
        ];

        return $map[$level] ?? $level;
    }
}
