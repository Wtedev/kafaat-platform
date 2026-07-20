<?php

namespace Tests\Feature\PrivacyPhase04;

use App\Enums\AuditLogResult;
use App\Enums\UserDocumentStatus;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\CreatesValidPdfUpload;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PrivateCvStorageTest extends TestCase
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

    public function test_cv_is_stored_on_private_disk_not_public(): void
    {
        $user = $this->makePortalUserWithProfile();
        $file = $this->validPdfUpload();

        $response = $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $file,
        ]);

        $response->assertRedirect()->assertSessionDoesntHaveErrors();

        $profile = $user->fresh()->profile;
        $this->assertNotNull($profile?->current_cv_document_id);

        $document = $profile?->currentCvDocument;
        $this->assertNotNull($document);
        $this->assertSame('private_documents', $document->disk);
        $this->assertTrue(Storage::disk('private_documents')->exists($document->path));
        $this->assertFalse(Storage::disk('public')->exists($document->path));
        $this->assertNull($user->fresh()->profile?->cvPublicUrl());
    }

    public function test_cv_path_and_disk_are_hidden_from_document_json(): void
    {
        $user = $this->makePortalUserWithProfile();

        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $document = $user->fresh()->profile?->currentCvDocument;
        $json = $document->toArray();

        $this->assertArrayNotHasKey('path', $json);
        $this->assertArrayNotHasKey('disk', $json);
        $this->assertArrayNotHasKey('sha256_checksum', $json);
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $user = $this->makePortalUserWithProfile();
        $file = UploadedFile::fake()->createWithContent('bad.pdf', 'NOT-A-PDF');

        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $file,
        ])->assertSessionHasErrors('cv');

        $this->assertNull($user->fresh()->profile?->current_cv_document_id);
    }

    public function test_owner_can_download_with_security_headers(): void
    {
        $user = $this->makePortalUserWithProfile();

        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertNotNull($user->fresh()->profile?->current_cv_document_id);

        $response = $this->actingAsOtpVerified($user)->get(route('portal.competency.cv.download'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cv.downloaded',
            'result' => AuditLogResult::Success->value,
            'target_user_id' => $user->id,
        ]);
    }

    public function test_other_user_cannot_download_cv(): void
    {
        $owner = $this->makePortalUserWithProfile(['email' => 'owner@example.com']);
        $other = $this->makePortalUserWithProfile(['email' => 'other@example.com']);

        $this->actingAsOtpVerified($owner)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ]);

        $this->actingAsOtpVerified($other)->get(route('portal.competency.cv.download'))->assertNotFound();
    }

    public function test_staff_with_permission_can_download_beneficiary_cv(): void
    {
        $beneficiary = $this->makePortalUserWithProfile(['email' => 'ben@example.com']);
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');
        $staff->givePermissionTo('candidate_pool.cv.download');

        $this->actingAsOtpVerified($beneficiary)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ]);

        $response = $this->actingAsOtpVerified($staff)
            ->get(route('admin.beneficiaries.cv-file.download', $beneficiary));

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cv.viewed',
            'actor_id' => $staff->id,
            'target_user_id' => $beneficiary->id,
        ]);
    }

    public function test_delete_cv_requires_password_and_removes_file(): void
    {
        $user = $this->makePortalUserWithProfile();
        // Factory default password is "password".

        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $document = $user->fresh()->profile?->currentCvDocument;
        $path = $document->path;

        $this->actingAsOtpVerified($user)->delete(route('portal.competency.cv.destroy'), [
            'password' => 'password',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertNull($user->fresh()->profile?->current_cv_document_id);
        $this->assertSame(UserDocumentStatus::Deleted, $document->fresh()->status);
        $this->assertFalse(Storage::disk('private_documents')->exists($path));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cv.deleted',
            'target_user_id' => $user->id,
        ]);
    }

    private function makePortalUserWithProfile(array $userAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'علي',
            'family_name' => 'السعود',
            'phone' => '0500000000',
        ], $userAttributes));
        $user->assignRole('beneficiary');
        Profile::query()->create([
            'user_id' => $user->id,
            'birth_date' => '1995-01-01',
        ]);

        return $user->fresh(['profile']);
    }
}
