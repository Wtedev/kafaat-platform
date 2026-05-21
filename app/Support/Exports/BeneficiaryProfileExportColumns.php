<?php

namespace App\Support\Exports;

use App\Enums\MembershipType;
use App\Models\Profile;
use App\Services\Portal\CvFormOptions;
use App\Services\Rbac\RbacCatalog;

/**
 * Column catalog for beneficiary profile Excel export.
 */
final class BeneficiaryProfileExportColumns
{
    /**
     * @return array<string, string> key => Arabic label
     */
    public static function optionLabels(): array
    {
        $out = [];
        foreach (self::definitions() as $key => $def) {
            $out[$key] = $def['label'];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return array_keys(array_filter(
            self::definitions(),
            fn (array $def): bool => $def['default'] ?? false,
        ));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function labelsForKeys(array $keys): array
    {
        $defs = self::definitions();

        return array_map(
            fn (string $key): string => $defs[$key]['label'] ?? $key,
            $keys,
        );
    }

    public static function resolve(Profile $profile, string $key): mixed
    {
        $profile->loadMissing('user.roles');
        $user = $profile->user;
        $locale = $profile->cvUiLocale();

        return match ($key) {
            'profile_id' => $profile->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_phone' => $user?->phone,
            'user_role_type' => self::roleTypeLabel($user?->role_type),
            'user_spatie_roles' => $user ? $user->filamentStaffRoleLabelsAr() : null,
            'user_is_active' => $user === null ? null : ($user->is_active ? 'نعم' : 'لا'),
            'user_last_login_at' => $user?->last_login_at?->format('Y-m-d H:i'),
            'gender' => match ($profile->gender) {
                'male' => 'ذكر',
                'female' => 'أنثى',
                default => null,
            },
            'birth_date' => $profile->birth_date?->format('Y-m-d'),
            'city' => $profile->city,
            'job_title' => $profile->job_title,
            'bio' => $profile->bio,
            'membership_type' => ($profile->membership_type instanceof MembershipType
                ? $profile->membership_type
                : MembershipType::tryFrom((string) $profile->membership_type))?->label(),
            'membership_badges' => implode(' + ', $profile->displayMembershipBadges()),
            'iconic_skill' => $profile->iconicSkillLabel(),
            'iconic_skill_style' => self::iconicSkillStyleLabel($profile->iconic_skill_style),
            'cv_language' => $profile->cv_language === 'en' ? 'English' : 'العربية',
            'cv_file_url' => $profile->cvPublicUrl(),
            'competency_english' => $profile->competency_levels['english'] ?? null,
            'competency_office' => $profile->competency_levels['office'] ?? null,
            'competency_courses' => $profile->competency_levels['courses'] ?? null,
            'competency_continuous_learning' => $profile->competency_levels['continuous_learning'] ?? null,
            'cv_skills' => self::formatSkills($profile),
            'cv_languages' => self::formatLanguages($profile),
            'cv_office_tools' => self::formatOfficeTools($profile),
            'cv_education' => self::formatEducation($profile),
            'cv_experience' => self::formatExperience($profile, $locale),
            'cv_external_courses' => self::formatExternalCourses($profile),
            'cv_links' => self::formatLinks($profile),
            'created_at' => $profile->created_at?->format('Y-m-d H:i'),
            'updated_at' => $profile->updated_at?->format('Y-m-d H:i'),
            default => null,
        };
    }

    /**
     * @return array<string, array{label: string, default?: bool}>
     */
    private static function definitions(): array
    {
        return [
            'profile_id' => ['label' => 'رقم الملف', 'default' => false],
            'user_name' => ['label' => 'الاسم', 'default' => true],
            'user_email' => ['label' => 'البريد الإلكتروني', 'default' => true],
            'user_phone' => ['label' => 'رقم الجوال', 'default' => true],
            'user_role_type' => ['label' => 'نوع الحساب', 'default' => true],
            'user_spatie_roles' => ['label' => 'أدوار النظام (Spatie)', 'default' => false],
            'user_is_active' => ['label' => 'الحساب نشط', 'default' => true],
            'user_last_login_at' => ['label' => 'آخر تسجيل دخول', 'default' => false],
            'gender' => ['label' => 'الجنس', 'default' => true],
            'birth_date' => ['label' => 'تاريخ الميلاد', 'default' => true],
            'city' => ['label' => 'المدينة', 'default' => true],
            'job_title' => ['label' => 'المسمى الوظيفي', 'default' => true],
            'bio' => ['label' => 'نبذة / السيرة', 'default' => false],
            'membership_type' => ['label' => 'نوع العضوية', 'default' => true],
            'membership_badges' => ['label' => 'شارات نوع المستفيد', 'default' => true],
            'iconic_skill' => ['label' => 'المهارة الأيقونية', 'default' => true],
            'iconic_skill_style' => ['label' => 'لون شارة المهارة', 'default' => false],
            'cv_language' => ['label' => 'لغة السيرة', 'default' => false],
            'cv_file_url' => ['label' => 'رابط ملف السيرة المرفوع', 'default' => false],
            'competency_english' => ['label' => 'مستوى الإنجليزية (بطاقات الكفاءات)', 'default' => false],
            'competency_office' => ['label' => 'مستوى الأوفيس (بطاقات الكفاءات)', 'default' => false],
            'competency_courses' => ['label' => 'مستوى الدورات (بطاقات الكفاءات)', 'default' => false],
            'competency_continuous_learning' => ['label' => 'التعلم المستمر (بطاقات الكفاءات)', 'default' => false],
            'cv_skills' => ['label' => 'المهارات (منشئ السيرة)', 'default' => false],
            'cv_languages' => ['label' => 'اللغات (منشئ السيرة)', 'default' => false],
            'cv_office_tools' => ['label' => 'أدوات الأوفيس (منشئ السيرة)', 'default' => false],
            'cv_education' => ['label' => 'التعليم (منشئ السيرة)', 'default' => false],
            'cv_experience' => ['label' => 'الخبرات (منشئ السيرة)', 'default' => false],
            'cv_external_courses' => ['label' => 'دورات خارجية (منشئ السيرة)', 'default' => false],
            'cv_links' => ['label' => 'الروابط (منشئ السيرة)', 'default' => false],
            'created_at' => ['label' => 'تاريخ إنشاء الملف', 'default' => false],
            'updated_at' => ['label' => 'آخر تحديث للملف', 'default' => false],
        ];
    }

    private static function roleTypeLabel(?string $roleType): ?string
    {
        return match ($roleType) {
            'beneficiary' => 'مستفيد',
            'trainee' => 'متدرب',
            'volunteer' => 'متطوع',
            'staff' => 'موظف',
            'admin' => 'مدير',
            default => filled($roleType) ? RbacCatalog::roleArabicLabel($roleType) : null,
        };
    }

    private static function iconicSkillStyleLabel(?string $style): ?string
    {
        return match ($style) {
            'amber' => 'ذهبي',
            'emerald' => 'أخضر',
            'sky' => 'أزرق',
            'rose' => 'وردي',
            'violet' => 'بنفسجي',
            'brand' => 'لون الهوية',
            default => null,
        };
    }

    private static function formatSkills(Profile $profile): ?string
    {
        $structured = $profile->cvSkillsStructured();
        if ($structured !== []) {
            return self::joinLines(array_map(
                fn (array $row): string => ($row['category'] ? "{$row['category']}: " : '')."{$row['skill_name']} ({$row['level']})",
                $structured,
            ));
        }

        return $profile->cvSkillsLegacyText();
    }

    private static function formatLanguages(Profile $profile): ?string
    {
        $structured = $profile->cvLanguagesStructured();
        if ($structured !== []) {
            return self::joinLines(array_map(
                fn (array $row): string => "{$row['language_name']} ({$row['level']})",
                $structured,
            ));
        }

        return $profile->cvLanguagesLegacyText();
    }

    private static function formatOfficeTools(Profile $profile): ?string
    {
        $rows = $profile->cvOfficeToolsStructured();
        if ($rows === []) {
            return null;
        }

        return self::joinLines(array_map(
            fn (array $row): string => "{$row['tool_name']} ({$row['level']})",
            $rows,
        ));
    }

    private static function formatEducation(Profile $profile): ?string
    {
        $structured = $profile->cvEducationStructured();
        if ($structured !== []) {
            return self::joinLines(array_map(function (array $row): string {
                $parts = array_filter([
                    $row['institution'],
                    $row['degree_or_program'],
                    $row['field'],
                    self::yearRange($row['start_year'], $row['end_year'], $row['is_current']),
                ]);

                return implode(' — ', $parts);
            }, $structured));
        }

        return $profile->cvEducationLegacyText();
    }

    private static function formatExperience(Profile $profile, string $locale): ?string
    {
        $structured = $profile->cvExperienceStructured();
        if ($structured !== []) {
            return self::joinLines(array_map(function (array $row) use ($locale): string {
                $mode = CvFormOptions::workModeLabel((string) $row['type'], $locale);
                $emp = CvFormOptions::employmentLabel((string) $row['employment_type'], $locale);
                $dates = self::yearRange($row['start_date'], $row['end_date'], $row['is_current']);
                $parts = array_filter([
                    $row['title'],
                    $row['organization'] !== '' ? $row['organization'] : null,
                    "{$mode} / {$emp}",
                    $dates,
                ]);

                return implode(' — ', $parts);
            }, $structured));
        }

        return $profile->cvExperienceLegacyText();
    }

    private static function formatExternalCourses(Profile $profile): ?string
    {
        $structured = $profile->cvExternalCoursesStructured();
        if ($structured !== []) {
            return self::joinLines(array_map(function (array $row): string {
                $parts = array_filter([$row['title'], $row['provider'], $row['date']]);

                return implode(' — ', $parts);
            }, $structured));
        }

        return $profile->cvExternalTrainingLegacyText();
    }

    private static function formatLinks(Profile $profile): ?string
    {
        $links = $profile->cvLinksList();
        if ($links === []) {
            return null;
        }

        return self::joinLines(array_map(
            fn (array $row): string => ($row['type'] ? "[{$row['type']}] " : '')."{$row['label']}: {$row['url']}",
            $links,
        ));
    }

    /**
     * @param  list<string>  $lines
     */
    private static function joinLines(array $lines): ?string
    {
        $lines = array_values(array_filter($lines, fn (string $l): bool => trim($l) !== ''));

        return $lines === [] ? null : implode("\n", $lines);
    }

    private static function yearRange(?string $start, ?string $end, bool $isCurrent): ?string
    {
        if (! filled($start) && ! filled($end)) {
            return $isCurrent ? 'حتى الآن' : null;
        }

        if ($isCurrent) {
            return trim((string) $start).' — حتى الآن';
        }

        return trim(implode(' — ', array_filter([$start, $end])));
    }
}
