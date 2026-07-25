<?php

namespace Tests\Feature\Storage;

use App\Enums\PrivacyExportFileStatus;
use App\Models\PrivacyExportFile;
use App\Models\Profile;
use App\Models\User;
use App\Services\Documents\PrivateDocumentsStorage;
use App\Services\Operations\ProductionEnvironmentValidator;
use App\Services\Operations\SystemHealthService;
use App\Services\Privacy\Export\PrivacyExportDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\CreatesValidPdfUpload;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

/**
 * Phase 2C-2 — Option B private disk (PRIVATE_DOCUMENTS_DISK=s3) without converting public media to S3.
 */
class DurablePrivateStorageTest extends TestCase
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
        Storage::fake('s3');
    }

    public function test_private_documents_disk_env_accepts_s3(): void
    {
        config([
            'cv.private_disk' => 's3',
            'privacy.export.disk' => 's3',
        ]);

        $this->assertSame('s3', PrivateDocumentsStorage::diskName());
        $this->assertSame('s3', (string) config('privacy.export.disk'));
        $this->assertArrayHasKey('s3', config('filesystems.disks'));
        $this->assertSame('s3', config('filesystems.disks.s3.driver'));
    }

    public function test_s3_disk_defaults_to_private_visibility_and_throws(): void
    {
        $disk = config('filesystems.disks.s3');

        $this->assertIsArray($disk);
        $this->assertSame('private', $disk['visibility'] ?? null);
        $this->assertTrue((bool) ($disk['throw'] ?? false));
        $this->assertSame('s3', $disk['driver'] ?? null);
    }

    public function test_public_disk_driver_stays_local_by_default(): void
    {
        $this->assertSame('local', config('filesystems.disks.public.driver'));
        $this->assertSame('public', config('filesystems.disks.public.visibility'));
    }

    public function test_cv_upload_with_s3_disk_never_uses_public_disk(): void
    {
        config([
            'cv.private_disk' => 's3',
            'privacy.export.disk' => 's3',
        ]);

        $user = $this->makePortalUserWithProfile();

        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $document = $user->fresh()->profile?->currentCvDocument;
        $this->assertNotNull($document);
        $this->assertSame('s3', $document->disk);
        $this->assertTrue(Storage::disk('s3')->exists($document->path));
        $this->assertFalse(Storage::disk('public')->exists($document->path));
        $this->assertFalse(Storage::disk('private_documents')->exists($document->path));
        $this->assertNull($user->fresh()->profile?->cvPublicUrl());
    }

    public function test_no_public_url_is_generated_for_private_cv_or_export_paths(): void
    {
        config([
            'cv.private_disk' => 's3',
            'privacy.export.disk' => 's3',
        ]);

        $user = $this->makePortalUserWithProfile();
        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect();

        $document = $user->fresh()->profile?->currentCvDocument;
        $this->assertNotNull($document);
        $this->assertNull($user->fresh()->profile?->cvPublicUrl());

        // App must serve private objects via authorized controllers — never Storage::url for CVs.
        $this->assertFalse(str_contains((string) $document->path, '/storage/'));
        $this->assertStringStartsWith('cv/', (string) $document->path);
    }

    public function test_owner_downloads_cv_via_authorized_controller_when_disk_is_s3(): void
    {
        config([
            'cv.private_disk' => 's3',
            'privacy.export.disk' => 's3',
        ]);

        $user = $this->makePortalUserWithProfile();
        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ])->assertRedirect();

        $response = $this->actingAsOtpVerified($user)->get(route('portal.competency.cv.download'));

        $response->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_export_download_uses_authorized_service_stream_on_s3_disk(): void
    {
        config([
            'cv.private_disk' => 's3',
            'privacy.export.disk' => 's3',
        ]);

        $owner = $this->makePortalUserWithProfile(['email' => 'export-owner@example.com']);
        $path = 'privacy-exports/'.Str::uuid().'/export.zip';
        Storage::disk('s3')->put($path, 'PK-fake-zip');

        $exportFile = PrivacyExportFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => $owner->id,
            'disk' => 's3',
            'path' => $path,
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->addDays(3),
            'size_bytes' => 12,
        ]);

        $request = Request::create('/portal/privacy/exports/'.$exportFile->uuid.'/download', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('sensitive_access_verified_at', now()->timestamp);

        $response = app(PrivacyExportDownloadService::class)->download($owner, $exportFile, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertSame(1, $exportFile->fresh()->download_count);
    }

    public function test_missing_private_object_fails_safely_without_public_fallback(): void
    {
        $owner = $this->makePortalUserWithProfile(['email' => 'missing-export@example.com']);
        $path = 'privacy-exports/missing/file.zip';

        $exportFile = PrivacyExportFile::query()->create([
            'uuid' => (string) Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => $owner->id,
            'disk' => 's3',
            'path' => $path,
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->addDays(3),
            'size_bytes' => 1,
        ]);

        $this->assertFalse(Storage::disk('s3')->exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));

        try {
            $request = Request::create('/portal/privacy/exports/'.$exportFile->uuid.'/download', 'POST');
            $request->setLaravelSession($this->app['session']->driver());
            $request->session()->put('sensitive_access_verified_at', now()->timestamp);

            app(PrivacyExportDownloadService::class)->download($owner, $exportFile, $request);
            $this->fail('Expected ValidationException when private object is missing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('export', $exception->errors());
        }
    }

    public function test_invalid_private_disk_name_fails_closed(): void
    {
        config(['cv.private_disk' => 'not-a-real-disk']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configured private documents disk is invalid.');

        PrivateDocumentsStorage::diskName();
    }

    public function test_system_health_reports_unreachable_private_disk_safely(): void
    {
        // Point health at a disk name that is not registered — must fail closed, not throw.
        config(['privacy.export.disk' => 'missing_private_disk_for_health']);

        $report = app(SystemHealthService::class)->check();
        $private = $report['checks']['private_disk'] ?? [];

        $this->assertSame('fail', $private['status'] ?? null);
        $this->assertSame('private_disk_unreachable', $private['message'] ?? null);
        $this->assertSame('degraded', $report['status']);
    }

    public function test_production_validator_flags_private_disk_when_rooted_at_public_storage(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://example.test',
            'security.force_https' => true,
            'security.trusted_hosts' => ['example.test'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'logging.channels.stack.channels' => ['stderr'],
            'mail.default' => 'resend',
            'privacy.export.disk' => 'private_documents',
            'filesystems.disks.private_documents.root' => storage_path('app/public'),
        ]);

        $issues = app(ProductionEnvironmentValidator::class)->violations();

        $this->assertTrue(
            collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'must not point to public storage')),
        );
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
