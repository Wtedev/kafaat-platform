<?php

namespace Tests\Unit\Services\Media;

use App\Services\Media\PublicMediaLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private PublicMediaLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->lifecycle = app(PublicMediaLifecycleService::class);
    }

    public function test_store_upload_returns_relative_uuid_path_under_directory(): void
    {
        $file = UploadedFile::fake()->image('cover.jpg', 400, 300);

        $path = $this->lifecycle->storeUpload($file, 'programs/covers');

        $this->assertMatchesRegularExpression(
            '#^programs/covers/[0-9a-f-]{36}\.jpg$#',
            $path,
        );
        Storage::disk('public')->assertExists($path);
        $this->assertStringNotContainsString('http', $path);
    }

    public function test_delete_owned_if_replaced_removes_previous_owned_file_only(): void
    {
        Storage::disk('public')->put('programs/covers/old.jpg', 'old');
        Storage::disk('public')->put('programs/covers/new.jpg', 'new');
        Storage::disk('public')->put('images/programs/bundled.jpg', 'bundled');

        $this->lifecycle->deleteOwnedIfReplaced('programs/covers/old.jpg', 'programs/covers/new.jpg');

        Storage::disk('public')->assertMissing('programs/covers/old.jpg');
        Storage::disk('public')->assertExists('programs/covers/new.jpg');

        $this->lifecycle->deleteOwnedIfReplaced('images/programs/bundled.jpg', 'programs/covers/new.jpg');
        Storage::disk('public')->assertExists('images/programs/bundled.jpg');
    }

    public function test_discard_failed_upload_deletes_new_owned_file(): void
    {
        Storage::disk('public')->put('news/images/orphan.jpg', 'x');

        $this->lifecycle->discardFailedUpload('news/images/orphan.jpg');

        Storage::disk('public')->assertMissing('news/images/orphan.jpg');
    }

    public function test_never_deletes_placeholder_or_git_images_prefix(): void
    {
        Storage::disk('public')->put('images/news-placeholder.svg', 'svg');

        $this->assertFalse($this->lifecycle->deleteOwnedPath('images/news-placeholder.svg'));
        Storage::disk('public')->assertExists('images/news-placeholder.svg');
    }

    public function test_is_owned_public_disk_path_for_known_prefixes(): void
    {
        $this->assertTrue($this->lifecycle->isOwnedPublicDiskPath('avatars/a.jpg'));
        $this->assertTrue($this->lifecycle->isOwnedPublicDiskPath('volunteer-opportunities/images/a.jpg'));
        $this->assertFalse($this->lifecycle->isOwnedPublicDiskPath('images/programs/x.jpg'));
        $this->assertFalse($this->lifecycle->isOwnedPublicDiskPath('../etc/passwd'));
    }
}
