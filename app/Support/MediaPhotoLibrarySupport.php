<?php

namespace App\Support;

use Illuminate\Support\Str;

final class MediaPhotoLibrarySupport
{
    /**
     * ترتيب أقسام المركز الإعلامي في الواجهة العامة.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'الفعاليات والمبادرات',
        'زيارات واستضافات',
        'مرافق الجمعية',
    ];

    /**
     * Media-center album preferred for the homepage hero banner.
     */
    public const HOMEPAGE_HERO_ALBUM = 'الاحتفاء باليوم العالمي للشباب';

    /**
     * Preferred basename within {@see HOMEPAGE_HERO_ALBUM} for the homepage hero
     * (wide 3:2 frame, cup toward camera — strongest banner read).
     */
    public const HOMEPAGE_HERO_PREFERRED_BASENAME = '7p0a2679';

    /**
     * Category used for marketing surfaces that must not show people.
     * Facility interiors are curated empty spaces (no faces/crowds).
     */
    public const PEOPLE_FREE_CATEGORY = 'مرافق الجمعية';

    /**
     * Album name fragments that typically depict people (events, visits, celebrations).
     * Used as a defensive filter when selecting people-free assets.
     *
     * @var list<string>
     */
    public const PEOPLE_CENTRIC_ALBUM_KEYWORDS = [
        'يوم الشباب',
        'الاحتفاء',
        'يوم مهارات الشباب',
        'معايدة',
        'استضافة',
        'زيارة',
        'ورشة',
        'ملتقى',
        'فعالية',
        'مبادرة',
        'منتدى',
        'تدشين',
        'احتفالية',
        'تطوع',
        'تأسيس',
        'المشاركة',
    ];

    /**
     * Canonical media-center albums (title + optional Y-m-d date).
     * Albums outside this catalog (except مرافق الجمعية) are removed from the public library.
     *
     * @var array<string, array{category: string, date: ?string}>
     */
    private const ALBUM_CATALOG = [
        // الفعاليات والمبادرات
        'ملتقى التطوعي الاحترافي للأيتام' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-10-01'],
        'احتفالية الموظفين بمناسبة اليوم الوطني السعودي ٩٥' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-09-25'],
        'الاحتفاء باليوم العالمي للغة العربية' => ['category' => 'الفعاليات والمبادرات', 'date' => '2024-12-19'],
        'ورشة عمل الرسم على الفخار للفريق التطوعي' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-11-11'],
        'الاحتفاء باليوم العالمي للشباب' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-08-14'],
        'الاحتفاء باليوم العالمي للدم، بالتعاون مع جمعية دمي' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-06-25'],
        'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-06-15'],
        'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-06-01'],
        'المشاركة في ملتقى مزارع' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-01-22'],
        'المشاركة في المعرض المصاحب لمنتدى العاملين مع الشباب' => ['category' => 'الفعاليات والمبادرات', 'date' => '2025-02-06'],

        // زيارات واستضافات
        'استضافة مؤسسة سليمان الراجحي الخيرية' => ['category' => 'زيارات واستضافات', 'date' => '2024-11-18'],
        'استضافة رجل الأعمال الوجيه عبدالعزيز التويجري' => ['category' => 'زيارات واستضافات', 'date' => '2025-03-06'],
        'استضافة جمعية مهارات الشباب في المذنب' => ['category' => 'زيارات واستضافات', 'date' => '2024-04-15'],
        // Source list had an invalid date (2025/25/25); omit until confirmed.
        'استضافة جمعية عنان للتنمية الذاتية في البدائع' => ['category' => 'زيارات واستضافات', 'date' => null],
        'استضافة مؤسسة حلم لتنظيم المعارض والمؤتمرات' => ['category' => 'زيارات واستضافات', 'date' => '2025-05-29'],
        'استضافة مساعد مدير التعليم في حائل ( سابقاً ) وعميد كلية العلوم والآداب في جامعة حائل' => ['category' => 'زيارات واستضافات', 'date' => '2025-06-01'],
        'استضافة بيت الثقافة | مكتبة بريدة العامة' => ['category' => 'زيارات واستضافات', 'date' => '2025-06-26'],
        'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر' => ['category' => 'زيارات واستضافات', 'date' => '2025-06-01'],
        'استضافة مؤسسة عبدالعزيز بن عبدالله الجميح الخيرية' => ['category' => 'زيارات واستضافات', 'date' => '2025-10-07'],
        'استضافة صندوق عائلة الحميد' => ['category' => 'زيارات واستضافات', 'date' => '2025-10-19'],
        'زيارة مدير إدارة الدعم في صندوق دعم الجمعيات: م. أنس الحازمي' => ['category' => 'زيارات واستضافات', 'date' => '2025-11-09'],
        'زيارة جمعية تحفيظ القرآن الكريم في القريات' => ['category' => 'زيارات واستضافات', 'date' => '2026-07-16'],
    ];

    /**
     * Legacy / short folder names → canonical album titles.
     *
     * @var array<string, string>
     */
    private const ALBUM_ALIASES = [
        // Events
        'ملتقى التطوع الاحترافي للأيتام' => 'ملتقى التطوعي الاحترافي للأيتام',
        'ملتقى التطوعي الاحترافي للأيتام' => 'ملتقى التطوعي الاحترافي للأيتام',
        'ورشة عمل الرسم على الفخار' => 'ورشة عمل الرسم على الفخار للفريق التطوعي',
        'ورشة عمل الرسم على الفخار للفريق التطوعي' => 'ورشة عمل الرسم على الفخار للفريق التطوعي',
        'يوم الشباب' => 'الاحتفاء باليوم العالمي للشباب',
        'الاحتفاء باليوم العالمي للشباب' => 'الاحتفاء باليوم العالمي للشباب',
        'مبادرة التبرع بالدم' => 'الاحتفاء باليوم العالمي للدم، بالتعاون مع جمعية دمي',
        'الاحتفاء باليوم العالمي للدم، بالتعاون مع جمعية دمي' => 'الاحتفاء باليوم العالمي للدم، بالتعاون مع جمعية دمي',
        'معايدة عيد الأضحى' => 'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك',
        'معايدة الموظفين - عيد الأضحى_' => 'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك',
        'معايدة الموظفين — عيد الأضحى' => 'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك',
        'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك' => 'حفل معايدة الموظفين بمناسبة عيد الأضحى المبارك',
        'احتفالية تأهل الجمعية في مبادرة آفاق' => 'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق',
        'فعالية افاق' => 'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق',
        'فعالية آفاق' => 'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق',
        'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق' => 'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق',
        'منتدى العاملين مع الشباب' => 'المشاركة في المعرض المصاحب لمنتدى العاملين مع الشباب',
        'المشاركة في المعرض المصاحب لمنتدى العاملين مع الشباب' => 'المشاركة في المعرض المصاحب لمنتدى العاملين مع الشباب',
        'احتفالية الموظفين بمناسبة اليوم الوطني السعودي ٩٥' => 'احتفالية الموظفين بمناسبة اليوم الوطني السعودي ٩٥',
        'الاحتفاء باليوم العالمي للغة العربية' => 'الاحتفاء باليوم العالمي للغة العربية',
        'المشاركة في ملتقى مزارع' => 'المشاركة في ملتقى مزارع',

        // Visits / hosting
        'استضافة مؤسسة سليمان الراجحي الخيرية' => 'استضافة مؤسسة سليمان الراجحي الخيرية',
        'استضافة عبدالعزيز التويجري' => 'استضافة رجل الأعمال الوجيه عبدالعزيز التويجري',
        'استضافة رجل الأعمال الوجيه عبدالعزيز التويجري' => 'استضافة رجل الأعمال الوجيه عبدالعزيز التويجري',
        'استضافة جمعية مهارات الشباب بالمذنب' => 'استضافة جمعية مهارات الشباب في المذنب',
        'استضافة جمعية مهارات الشباب في المذنب' => 'استضافة جمعية مهارات الشباب في المذنب',
        'زيارة جمعية عنان' => 'استضافة جمعية عنان للتنمية الذاتية في البدائع',
        'استضافة جمعية عنان للتنمية الذاتية في البدائع' => 'استضافة جمعية عنان للتنمية الذاتية في البدائع',
        'شركة حلم' => 'استضافة مؤسسة حلم لتنظيم المعارض والمؤتمرات',
        'استضافة مؤسسة حلم لتنظيم المعارض والمؤتمرات' => 'استضافة مؤسسة حلم لتنظيم المعارض والمؤتمرات',
        'زيارة مساعد مدير تعليم حايل' => 'استضافة مساعد مدير التعليم في حائل ( سابقاً ) وعميد كلية العلوم والآداب في جامعة حائل',
        'استضافة مساعد مدير التعليم في حائل ( سابقاً ) وعميد كلية العلوم والآداب في جامعة حائل' => 'استضافة مساعد مدير التعليم في حائل ( سابقاً ) وعميد كلية العلوم والآداب في جامعة حائل',
        'بيت الثقافة' => 'استضافة بيت الثقافة | مكتبة بريدة العامة',
        'استضافة بيت الثقافة | مكتبة بريدة العامة' => 'استضافة بيت الثقافة | مكتبة بريدة العامة',
        'عبدالله الحبيتر' => 'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر',
        'استضافة عبدالله الحبيتر' => 'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر',
        'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر' => 'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر',
        'زيارة مؤسسة عبدالعزيز الجميح الخيرية' => 'استضافة مؤسسة عبدالعزيز بن عبدالله الجميح الخيرية',
        'استضافة مؤسسة عبدالعزيز بن عبدالله الجميح الخيرية' => 'استضافة مؤسسة عبدالعزيز بن عبدالله الجميح الخيرية',
        'استضافة صندوق عائلة الحميد' => 'استضافة صندوق عائلة الحميد',
        'زيارة مدير إدارة الدعم بصندوق دعم الجمعيات' => 'زيارة مدير إدارة الدعم في صندوق دعم الجمعيات: م. أنس الحازمي',
        'زيارة مدير إدارة الدعم في صندوق دعم الجمعيات: م. أنس الحازمي' => 'زيارة مدير إدارة الدعم في صندوق دعم الجمعيات: م. أنس الحازمي',
        'زيارة جمعية تحفيظ القرآن الكريم في القريات' => 'زيارة جمعية تحفيظ القرآن الكريم في القريات',
    ];

    /**
     * @return list<string>
     */
    public static function canonicalAlbumTitles(): array
    {
        return array_keys(self::ALBUM_CATALOG);
    }

    /**
     * @return array<string, array{category: string, date: ?string}>
     */
    public static function albumCatalog(): array
    {
        return self::ALBUM_CATALOG;
    }

    public static function normalizeFolderName(string $name): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $normalized = rtrim($normalized, '_');

        return self::ALBUM_ALIASES[$normalized] ?? $normalized;
    }

    public static function albumLabel(string $category, string $folderName): string
    {
        $album = self::normalizeFolderName($folderName);

        if ($album === $category) {
            return $category;
        }

        return $album;
    }

    public static function albumDate(?string $album): ?string
    {
        if ($album === null || $album === '') {
            return null;
        }

        $canonical = self::normalizeFolderName($album);

        return self::ALBUM_CATALOG[$canonical]['date'] ?? null;
    }

    public static function albumSortTimestamp(?string $album): int
    {
        $date = self::albumDate($album);

        if ($date === null) {
            return 0;
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? 0 : $timestamp;
    }

    /**
     * Whether an album may appear in the public media library.
     * Facility albums are always allowed; curated event/visit albums must be in the catalog.
     */
    public static function isAllowedAlbum(string $category, ?string $album): bool
    {
        if ($category === self::PEOPLE_FREE_CATEGORY) {
            return true;
        }

        if ($album === null || trim($album) === '') {
            return false;
        }

        $canonical = self::normalizeFolderName($album);

        if ($canonical === $category) {
            return false;
        }

        return array_key_exists($canonical, self::ALBUM_CATALOG);
    }

    public static function photoCaption(string $category, string $album): string
    {
        if ($album === $category) {
            return $category.' — جمعية كفاءات';
        }

        $date = self::albumDate($album);
        $base = $album.' — '.$category;

        if ($date === null) {
            return $base;
        }

        return $base.' — '.ar_date($date, 'd MMMM y');
    }

    public static function photoTitle(string $filename, string $album, int $index): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $humanized = Str::of($base)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();

        if ($humanized === '' || preg_match('/^(img|dsc|7p0a|unnamed|[0-9a-f-]{20,})/i', $humanized)) {
            return $index > 0 ? $album.' — صورة '.($index + 1) : $album;
        }

        return $humanized;
    }

    public static function categoryDescription(string $category): string
    {
        return match ($category) {
            'الفعاليات والمبادرات' => 'لقطات من فعالياتنا ومبادراتنا المجتمعية والتدريبية.',
            'زيارات واستضافات' => 'زيارات واستضافات الشركاء والجهات الداعمة لمسيرة كفاءات.',
            'مرافق الجمعية' => 'جولة في مقر ومرافق جمعية كفاءات.',
            default => 'صور من أرشيف جمعية كفاءات.',
        };
    }

    public static function isPeopleCentricAlbum(?string $album): bool
    {
        if ($album === null || trim($album) === '') {
            return false;
        }

        foreach (self::PEOPLE_CENTRIC_ALBUM_KEYWORDS as $keyword) {
            if (str_contains($album, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
