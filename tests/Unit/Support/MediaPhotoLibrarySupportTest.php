<?php

namespace Tests\Unit\Support;

use App\Support\MediaPhotoLibrarySupport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaPhotoLibrarySupportTest extends TestCase
{
    #[DataProvider('aliasProvider')]
    public function test_normalizes_legacy_album_names_to_canonical_titles(string $legacy, string $canonical): void
    {
        $this->assertSame($canonical, MediaPhotoLibrarySupport::normalizeFolderName($legacy));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function aliasProvider(): array
    {
        return [
            'youth day' => ['يوم الشباب', 'الاحتفاء باليوم العالمي للشباب'],
            'pottery workshop' => ['ورشة عمل الرسم على الفخار', 'ورشة عمل الرسم على الفخار للفريق التطوعي'],
            'afaq short' => ['فعالية افاق', 'احتفالية الموظفين بمناسبة تأهل الجمعية في مبادرة آفاق'],
            'blood drive' => ['مبادرة التبرع بالدم', 'الاحتفاء باليوم العالمي للدم، بالتعاون مع جمعية دمي'],
            'halm' => ['شركة حلم', 'استضافة مؤسسة حلم لتنظيم المعارض والمؤتمرات'],
            'hubaytir' => ['عبدالله الحبيتر', 'استضافة استشاري التميز المؤسسي والجودة أ. عبدالله الحبيتر'],
        ];
    }

    public function test_album_dates_match_approved_catalog(): void
    {
        $this->assertSame('2025-08-14', MediaPhotoLibrarySupport::albumDate('يوم الشباب'));
        $this->assertSame('2025-10-01', MediaPhotoLibrarySupport::albumDate('ملتقى التطوعي الاحترافي للأيتام'));
        $this->assertSame('2025-11-09', MediaPhotoLibrarySupport::albumDate('زيارة مدير إدارة الدعم بصندوق دعم الجمعيات'));
        $this->assertNull(MediaPhotoLibrarySupport::albumDate('استضافة جمعية عنان للتنمية الذاتية في البدائع'));
    }

    public function test_unlisted_albums_are_rejected_outside_facilities(): void
    {
        $this->assertFalse(MediaPhotoLibrarySupport::isAllowedAlbum('الفعاليات والمبادرات', 'يوم التأسيس'));
        $this->assertFalse(MediaPhotoLibrarySupport::isAllowedAlbum('زيارات واستضافات', 'مقهى وليف'));
        $this->assertTrue(MediaPhotoLibrarySupport::isAllowedAlbum('الفعاليات والمبادرات', 'يوم الشباب'));
        $this->assertTrue(MediaPhotoLibrarySupport::isAllowedAlbum('مرافق الجمعية', 'مرافق كفاءات'));
    }

    public function test_homepage_hero_album_matches_youth_day_canonical_title(): void
    {
        $this->assertSame(
            'الاحتفاء باليوم العالمي للشباب',
            MediaPhotoLibrarySupport::HOMEPAGE_HERO_ALBUM,
        );
    }
}
