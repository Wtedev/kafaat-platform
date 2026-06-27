<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CvBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_current_cv_storage_uses_public_disk_as_documented_baseline(): void
    {
        Storage::fake('public');

        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertRedirect();

        $path = $user->fresh()->profile?->cv_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('cv/', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_cv_upload_rejects_disallowed_mime_type(): void
    {
        Storage::fake('public');

        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->create('resume.exe', 100, 'application/octet-stream');

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertSessionHasErrors('cv');

        $this->assertNull($user->fresh()->profile?->cv_path);
    }

    public function test_cv_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->create('resume.pdf', 11000, 'application/pdf');

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertSessionHasErrors('cv');
    }

    public function test_cv_update_only_affects_authenticated_user(): void
    {
        Storage::fake('public');

        $owner = $this->makePortalUserWithProfile(['email' => 'owner@example.com']);
        $other = $this->makePortalUserWithProfile(['email' => 'other@example.com']);
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $this->actingAsOtpVerified($other)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertRedirect();

        $this->assertNull($owner->fresh()->profile?->cv_path);
        $this->assertNotNull($other->fresh()->profile?->cv_path);
    }

    public function test_competency_pdf_export_is_available_to_owner(): void
    {
        $user = $this->makePortalUserWithProfile(['name' => 'مستفيد تجريبي']);

        $response = $this->actingAsOtpVerified($user)
            ->get(route('portal.competency.export-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function makePortalUserWithProfile(array $userAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $userAttributes));
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh(['profile']);
    }
}
