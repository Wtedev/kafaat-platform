<?php

namespace App\Services\Portal;

/**
 * UI / PDF labels only (never user-authored CV text).
 */
final class CvUiTranslator
{
    /**
     * @return array<string, string>
     */
    public static function sectionLabels(string $locale): array
    {
        $en = [
            'summary' => 'Summary',
            'skills' => 'Skills',
            'languages' => 'Languages',
            'tools' => 'Digital tools',
            'education' => 'Education',
            'experience' => 'Experience',
            'courses' => 'Courses & certifications',
            'links' => 'Links',
            'platform' => 'Platform highlights',
            'recommendations' => 'Recommendations',
            'legacy_summary' => 'Legacy summary',
            'city' => 'City',
            'email' => 'Email',
            'current' => 'Present',
            'kafaat' => 'Kafaat platform',
            'volunteering' => 'Volunteering',
            'program_certificate' => 'Program certificate',
            'learning_paths' => 'Learning paths',
            'volunteer_hours' => 'Approved volunteer hours',
            'platform_auto' => 'Synced from platform',
            'footer_note' => 'Generated from Kafaat platform',
        ];

        $ar = [
            'summary' => 'نبذة',
            'skills' => 'المهارات',
            'languages' => 'اللغات',
            'tools' => 'الأدوات الرقمية',
            'education' => 'التعليم',
            'experience' => 'الخبرات',
            'courses' => 'الدورات والشهادات',
            'links' => 'روابط مهمة',
            'platform' => 'إنجازات المنصة',
            'recommendations' => 'التوصيات',
            'legacy_summary' => 'ملخص قديم',
            'city' => 'المدينة',
            'email' => 'البريد',
            'current' => 'حتى الآن',
            'kafaat' => 'منصة كفاءات',
            'volunteering' => 'تطوع',
            'program_certificate' => 'شهادة برنامج',
            'learning_paths' => 'المسارات التعليمية',
            'volunteer_hours' => 'ساعات التطوع المعتمدة',
            'platform_auto' => 'مزامن من المنصة',
            'footer_note' => 'وثيقة مُنشأة من منصة كفاءات',
        ];

        return $locale === 'en' ? $en : $ar;
    }

    public static function t(string $locale, string $key): string
    {
        return self::sectionLabels($locale)[$key] ?? $key;
    }
}
