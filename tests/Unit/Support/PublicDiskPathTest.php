<?php

namespace Tests\Unit\Support;

use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDiskPathTest extends TestCase
{
    public function test_ephemeral_livewire_preview_urls_are_detected(): void
    {
        $url = 'https://example.test/livewire/preview-file/abc.jpg?expires=123&signature=xyz';

        $this->assertTrue(PublicDiskPath::isEphemeralUploadUrl($url));
        $this->assertNull(PublicDiskPath::normalize($url));
        $this->assertNull(PublicDiskPath::url($url));
        $this->assertSame('abc.jpg', PublicDiskPath::livewirePreviewFilename($url));
    }

    public function test_url_or_placeholder_falls_back_for_ephemeral_urls(): void
    {
        $url = 'https://example.test/livewire/preview-file/abc.jpg?expires=123&signature=xyz';
        $resolved = PublicDiskPath::urlOrPlaceholder($url);

        $this->assertStringContainsString('news-placeholder.svg', $resolved);
    }

    public function test_relative_path_from_public_storage_url(): void
    {
        $this->assertSame(
            'news/images/photo.jpg',
            PublicDiskPath::relativePathFromPublicUrl('https://example.test/storage/news/images/photo.jpg'),
        );
    }

    public function test_durable_relative_paths_still_resolve(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('news/images/ok.jpg', 'bytes');

        $this->assertSame('/storage/news/images/ok.jpg', PublicDiskPath::url('news/images/ok.jpg'));
    }
}
