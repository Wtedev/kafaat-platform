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
     * @var array<string, string>
     */
    private const ALBUM_ALIASES = [
        'فعالية افاق' => 'فعالية آفاق',
        'زيارة لجنة تحكيم المبادرات - افاق -' => 'زيارة لجنة تحكيم المبادرات — آفاق',
        'عبدالله الحبيتر' => 'استضافة عبدالله الحبيتر',
        'معايدة الموظفين - عيد الأضحى_' => 'معايدة الموظفين — عيد الأضحى',
        'معايدة الموظفين - عيد الفطر' => 'معايدة الموظفين — عيد الفطر',
        'مبادرة التبرع بالدم' => 'مبادرة التبرع بالدم',
        'ورشة عمل الرسم على الفخار' => 'ورشة عمل الرسم على الفخار',
        'يوم الشباب' => 'يوم الشباب',
        'يوم مهارات الشباب' => 'يوم مهارات الشباب',
        'زيارة جمعية الإحسان' => 'زيارة جمعية الإحسان',
        'زيارة مؤسسة عبدالعزيز الجميح الخيرية' => 'زيارة مؤسسة عبدالعزيز الجميح الخيرية',
        'زيارة مدير إدارة الدعم بصندوق دعم الجمعيات' => 'زيارة مدير إدارة الدعم بصندوق دعم الجمعيات',
    ];

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

    public static function photoCaption(string $category, string $album): string
    {
        if ($album === $category) {
            return $category.' — جمعية كفاءات';
        }

        return $album.' — '.$category;
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
}
