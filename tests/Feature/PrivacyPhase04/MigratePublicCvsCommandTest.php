<?php

namespace Tests\Feature\PrivacyPhase04;

use App\Enums\UserDocumentStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigratePublicCvsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private_documents');
    }

    public function test_dry_run_does_not_modify_database_or_storage(): void
    {
        [$user, $legacyPath] = $this->seedLegacyPublicCv();

        Artisan::call('privacy:migrate-public-cvs', ['--dry-run' => true]);

        $this->assertTrue(Storage::disk('public')->exists($legacyPath));
        $this->assertSame($legacyPath, $user->fresh()->profile->cv_path);
        $this->assertNull($user->fresh()->profile->current_cv_document_id);
        $this->assertSame(0, UserDocument::query()->count());
    }

    public function test_migration_moves_file_to_private_disk_and_clears_public_path(): void
    {
        [$user, $legacyPath] = $this->seedLegacyPublicCv();

        Artisan::call('privacy:migrate-public-cvs');

        $profile = $user->fresh()->profile;
        $this->assertNull($profile->cv_path);
        $this->assertNotNull($profile->current_cv_document_id);
        $this->assertFalse(Storage::disk('public')->exists($legacyPath));

        $document = $profile->currentCvDocument;
        $this->assertTrue(Storage::disk('private_documents')->exists($document->path));
        $this->assertSame(UserDocumentStatus::Active, $document->status);
    }

    public function test_migration_is_idempotent(): void
    {
        [$user] = $this->seedLegacyPublicCv();

        Artisan::call('privacy:migrate-public-cvs');
        Artisan::call('privacy:migrate-public-cvs');

        $this->assertSame(1, UserDocument::query()->where('user_id', $user->id)->count());
    }

    public function test_missing_public_file_is_reported_without_creating_document(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        Profile::query()->create([
            'user_id' => $user->id,
            'cv_path' => 'cv/missing.pdf',
        ]);

        $exitCode = Artisan::call('privacy:migrate-public-cvs');

        $this->assertSame(0, $exitCode);
        $this->assertNull($user->fresh()->profile->current_cv_document_id);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function seedLegacyPublicCv(): array
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $legacyPath = 'cv/legacy-'.Str::uuid().'.pdf';
        Storage::disk('public')->put($legacyPath, '%PDF-1.4 legacy');
        Profile::query()->create([
            'user_id' => $user->id,
            'cv_path' => $legacyPath,
        ]);

        return [$user->fresh(), $legacyPath];
    }
}
