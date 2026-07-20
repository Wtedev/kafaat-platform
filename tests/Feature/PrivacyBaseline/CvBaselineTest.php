<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\CreatesValidPdfUpload;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CvBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use CreatesValidPdfUpload;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Storage::fake('public');
        Storage::fake('private_documents');
    }

    public function test_cv_storage_uses_private_disk(): void
    {
        $user = $this->makePortalUserWithProfile();
        $file = $this->validPdfUpload();

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertRedirect();

        $document = $user->fresh()->profile?->currentCvDocument;
        $this->assertNotNull($document);
        $this->assertTrue(Storage::disk('private_documents')->exists($document->path));
        $this->assertNull($user->fresh()->profile?->cvPublicUrl());
    }

    public function test_cv_upload_rejects_disallowed_mime_type(): void
    {
        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->create('resume.exe', 100, 'application/octet-stream');

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertSessionHasErrors('cv');

        $this->assertNull($user->fresh()->profile?->current_cv_document_id);
    }

    public function test_cv_upload_rejects_oversized_file(): void
    {
        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->createWithContent(
            'resume.pdf',
            '%PDF-1.4'.str_repeat('x', 11 * 1024 * 1024),
        );

        $this->actingAsOtpVerified($user)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertSessionHasErrors('cv');
    }

    public function test_cv_update_only_affects_authenticated_user(): void
    {
        $owner = $this->makePortalUserWithProfile(['email' => 'owner@example.com']);
        $other = $this->makePortalUserWithProfile(['email' => 'other@example.com']);
        $file = $this->validPdfUpload();

        $this->actingAsOtpVerified($other)->patch(route('portal.competency.update'), [
            'section' => 'cv_attachment',
            'cv' => $file,
        ])->assertRedirect();

        $this->assertNull($owner->fresh()->profile?->current_cv_document_id);
        $this->assertNotNull($other->fresh()->profile?->current_cv_document_id);
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
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $userAttributes));
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh(['profile']);
    }
}
