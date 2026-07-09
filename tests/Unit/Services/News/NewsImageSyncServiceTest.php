<?php

namespace Tests\Unit\Services\News;

use App\Models\News;
use App\Services\News\NewsImageSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsImageSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function seedFile(string $path): void
    {
        Storage::disk('public')->put($path, 'image-bytes');
    }

    public function test_sync_creates_images_and_sets_single_primary(): void
    {
        $news = News::create([
            'title' => 'خبر تجريبي',
            'slug' => 'test-news-images',
            'content' => '<p>محتوى</p>',
        ]);

        $this->seedFile('news/images/a.jpg');
        $this->seedFile('news/images/b.jpg');
        $this->seedFile('news/images/c.jpg');

        app(NewsImageSyncService::class)->sync($news, [
            ['path' => 'news/images/a.jpg', 'is_primary' => false],
            ['path' => 'news/images/b.jpg', 'is_primary' => true],
            ['path' => 'news/images/c.jpg', 'is_primary' => true],
        ], allowEmpty: true);

        $news->refresh();

        $this->assertCount(3, $news->images);
        $this->assertSame('news/images/b.jpg', $news->image);
        $this->assertSame(1, $news->images()->where('is_primary', true)->count());
        $this->assertTrue($news->images()->where('path', 'news/images/b.jpg')->value('is_primary'));
    }

    public function test_sync_defaults_first_image_as_primary_when_none_selected(): void
    {
        $news = News::create([
            'title' => 'خبر بدون أساسية',
            'slug' => 'test-news-default-primary',
            'content' => 'نص',
        ]);

        $this->seedFile('news/images/one.jpg');
        $this->seedFile('news/images/two.jpg');

        app(NewsImageSyncService::class)->sync($news, [
            ['path' => 'news/images/one.jpg', 'is_primary' => false],
            ['path' => 'news/images/two.jpg', 'is_primary' => false],
        ], allowEmpty: true);

        $news->refresh();

        $this->assertSame('news/images/one.jpg', $news->image);
        $this->assertTrue($news->images()->where('path', 'news/images/one.jpg')->value('is_primary'));
    }

    public function test_sync_with_empty_rows_preserves_existing_images_by_default(): void
    {
        $news = News::create([
            'title' => 'خبر محفوظ',
            'slug' => 'test-news-preserve-images',
            'content' => 'نص',
        ]);

        $this->seedFile('news/images/kept.jpg');

        app(NewsImageSyncService::class)->sync($news, [
            ['path' => 'news/images/kept.jpg', 'is_primary' => true],
        ], allowEmpty: true);

        app(NewsImageSyncService::class)->sync($news, []);

        $news->refresh();

        $this->assertCount(1, $news->images);
        $this->assertSame('news/images/kept.jpg', $news->image);
    }

    public function test_sync_with_empty_rows_can_clear_images_when_allowed(): void
    {
        $news = News::create([
            'title' => 'خبر يُفرَّغ',
            'slug' => 'test-news-clear-images',
            'content' => 'نص',
        ]);

        $this->seedFile('news/images/remove-me.jpg');

        app(NewsImageSyncService::class)->sync($news, [
            ['path' => 'news/images/remove-me.jpg', 'is_primary' => true],
        ], allowEmpty: true);

        app(NewsImageSyncService::class)->sync($news, [], allowEmpty: true);

        $news->refresh();

        $this->assertCount(0, $news->images);
        $this->assertNull($news->image);
    }

    public function test_migrate_legacy_image_creates_primary_row(): void
    {
        $news = News::create([
            'title' => 'خبر قديم',
            'slug' => 'legacy-news-image',
            'content' => 'نص',
            'image' => 'news/images/legacy.jpg',
        ]);

        app(NewsImageSyncService::class)->migrateLegacyImageIfNeeded($news);

        $this->assertDatabaseHas('news_images', [
            'news_id' => $news->id,
            'path' => 'news/images/legacy.jpg',
            'is_primary' => true,
        ]);
    }
}
