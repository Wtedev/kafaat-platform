<?php

namespace App\Services\Portal;

/**
 * World-language presets for CV language rows (ISO 639-1 codes where standard).
 * UI labels: Arabic + English names.
 */
final class CvLanguagePresets
{
    /** @var list<string> */
    public const CODES = [
        'ar', 'en', 'fr', 'es', 'de', 'it', 'pt', 'tr', 'zh', 'ja', 'ko', 'hi', 'ur', 'fa', 'ru',
        'id', 'ms', 'sw', 'nl', 'el', 'he', 'bn', 'fil', 'vi', 'th',
        'custom',
    ];

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function names(): array
    {
        return [
            'ar' => ['ar' => 'العربية', 'en' => 'Arabic'],
            'en' => ['ar' => 'الإنجليزية', 'en' => 'English'],
            'fr' => ['ar' => 'الفرنسية', 'en' => 'French'],
            'es' => ['ar' => 'الإسبانية', 'en' => 'Spanish'],
            'de' => ['ar' => 'الألمانية', 'en' => 'German'],
            'it' => ['ar' => 'الإيطالية', 'en' => 'Italian'],
            'pt' => ['ar' => 'البرتغالية', 'en' => 'Portuguese'],
            'tr' => ['ar' => 'التركية', 'en' => 'Turkish'],
            'zh' => ['ar' => 'الصينية (ماندرين)', 'en' => 'Chinese (Mandarin)'],
            'ja' => ['ar' => 'اليابانية', 'en' => 'Japanese'],
            'ko' => ['ar' => 'الكورية', 'en' => 'Korean'],
            'hi' => ['ar' => 'الهندية', 'en' => 'Hindi'],
            'ur' => ['ar' => 'الأردية', 'en' => 'Urdu'],
            'fa' => ['ar' => 'الفارسية', 'en' => 'Persian (Farsi)'],
            'ru' => ['ar' => 'الروسية', 'en' => 'Russian'],
            'id' => ['ar' => 'الإندونيسية', 'en' => 'Indonesian'],
            'ms' => ['ar' => 'الملايو', 'en' => 'Malay'],
            'sw' => ['ar' => 'السواحيلية', 'en' => 'Swahili'],
            'nl' => ['ar' => 'الهولندية', 'en' => 'Dutch'],
            'el' => ['ar' => 'اليونانية', 'en' => 'Greek'],
            'he' => ['ar' => 'العبرية', 'en' => 'Hebrew'],
            'bn' => ['ar' => 'البنغالية', 'en' => 'Bengali'],
            'fil' => ['ar' => 'الفلبينية', 'en' => 'Filipino'],
            'vi' => ['ar' => 'الفيتنامية', 'en' => 'Vietnamese'],
            'th' => ['ar' => 'التايلاندية', 'en' => 'Thai'],
            'custom' => ['ar' => 'أخرى', 'en' => 'Other'],
        ];
    }

    public static function label(string $code, string $locale): string
    {
        $names = self::names();

        return $names[$code][$locale === 'en' ? 'en' : 'ar'] ?? $code;
    }
}
