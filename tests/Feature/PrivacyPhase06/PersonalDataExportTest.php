<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\PrivacyExportFileStatus;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Jobs\GeneratePersonalDataExport;
use App\Models\PrivacyExportFile;
use App\Models\PrivacyRequest;
use App\Models\Profile;
use App\Models\User;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Privacy\Export\PersonalDataExportService;
use App\Services\Privacy\PrivacyRequestService;
use Database\Seeders\RetentionPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PersonalDataExportTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('portal.privacy')) {
            $this->markTestSkipped('Portal privacy center routes were removed from the portal.');
        }

        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
        $this->seed(RetentionPolicySeeder::class);
        Storage::fake('private_documents');
    }

    public function test_export_request_requires_password_and_logs_events(): void
    {
        Queue::fake();
        $user = $this->makeBeneficiary('export-req@example.com');
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.export'), [])
            ->assertSessionHasErrors('password');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.export'), ['password' => 'SecretPass1!'])
            ->assertRedirect(route('portal.privacy'));

        $request = PrivacyRequest::query()->where('user_id', $user->id)->first();
        $this->assertSame(PrivacyRequestType::DataExport, $request->request_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'privacy_export.request_created',
            'result' => AuditLogResult::Success->value,
        ]);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_export_requested',
        ]);
    }

    public function test_duplicate_active_export_request_is_blocked(): void
    {
        $user = $this->makeBeneficiary('dup-export@example.com');
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)->post(route('portal.privacy.requests.export'), ['password' => 'SecretPass1!']);
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.export'), ['password' => 'SecretPass1!'])
            ->assertRedirect(route('portal.privacy'))
            ->assertSessionHas('error');
    }

    public function test_job_payload_contains_no_pii_and_generation_produces_private_zip(): void
    {
        $beneficiary = $this->makeBeneficiary('zip-gen@example.com');
        $officer = $this->makeOfficer([
            'privacy_requests.review',
            'privacy_requests.approve',
            'privacy_requests.export.generate',
        ]);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataExport($beneficiary, $httpRequest, 'SecretPass1!');
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);

        Queue::fake();
        app(PersonalDataExportService::class)->dispatchGeneration($privacyRequest->fresh(), $officer);

        Queue::assertPushed(GeneratePersonalDataExport::class, function (GeneratePersonalDataExport $job) use ($privacyRequest): bool {
            $this->assertSame($privacyRequest->id, $job->privacyRequestId);

            return true;
        });

        app(PersonalDataExportService::class)->generateForRequest($privacyRequest->id);

        $exportFile = PrivacyExportFile::query()->where('privacy_request_id', $privacyRequest->id)->first();
        $this->assertNotNull($exportFile);
        $this->assertSame(PrivacyExportFileStatus::Ready, $exportFile->status);
        $this->assertNotNull($exportFile->expires_at);
        Storage::disk('private_documents')->assertExists($exportFile->path);

        $privacyRequest->refresh();
        $this->assertSame(PrivacyRequestStatus::Completed, $privacyRequest->status);
    }

    public function test_export_zip_json_allowlist_excludes_secrets(): void
    {
        $beneficiary = $this->makeBeneficiary('allowlist@example.com');
        $officer = $this->makeOfficer(['privacy_requests.approve', 'privacy_requests.export.generate']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataExport($beneficiary, $httpRequest, 'SecretPass1!');
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);
        app(PersonalDataExportService::class)->dispatchGeneration($privacyRequest->fresh(), $officer);
        app(PersonalDataExportService::class)->generateForRequest($privacyRequest->id);

        $exportFile = PrivacyExportFile::query()->where('privacy_request_id', $privacyRequest->id)->firstOrFail();
        $zipContent = Storage::disk('private_documents')->get($exportFile->path);
        $this->assertNotEmpty($zipContent);

        $tmp = tempnam(sys_get_temp_dir(), 'export-test');
        file_put_contents($tmp, $zipContent);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $accountJson = $zip->getFromName('account.json');
        $zip->close();
        unlink($tmp);

        $this->assertIsString($accountJson);
        $this->assertStringNotContainsString('password', strtolower($accountJson));
        $this->assertStringNotContainsString('ciphertext', strtolower($accountJson));
        $this->assertStringNotContainsString('lookup_hash', strtolower($accountJson));
        $this->assertStringContainsString('identity_masked', $accountJson);
    }

    public function test_owner_can_download_ready_export_with_verification(): void
    {
        $beneficiary = $this->makeBeneficiary('download@example.com');
        $officer = $this->makeOfficer(['privacy_requests.approve', 'privacy_requests.export.generate']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataExport($beneficiary, $httpRequest, 'SecretPass1!');
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);
        app(PersonalDataExportService::class)->dispatchGeneration($privacyRequest->fresh(), $officer);
        app(PersonalDataExportService::class)->generateForRequest($privacyRequest->id);

        $exportFile = PrivacyExportFile::query()->where('privacy_request_id', $privacyRequest->id)->firstOrFail();

        $response = $this->actingAsOtpVerified($beneficiary)
            ->withSession(['sensitive_access_verified_at' => now()->timestamp])
            ->post(route('portal.privacy.exports.download', $exportFile));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, $exportFile->fresh()->download_count);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'privacy_export.downloaded',
            'actor_id' => $beneficiary->id,
        ]);
    }

    public function test_other_user_cannot_download_export(): void
    {
        $owner = $this->makeBeneficiary('owner-export@example.com');
        $other = $this->makeBeneficiary('other-export@example.com');

        $exportFile = PrivacyExportFile::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'privacy_request_id' => null,
            'user_id' => $owner->id,
            'disk' => 'private_documents',
            'path' => 'privacy-exports/test/file.zip',
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->addDays(3),
            'size_bytes' => 100,
        ]);
        Storage::disk('private_documents')->put($exportFile->path, 'fake');

        $this->actingAsOtpVerified($other)
            ->post(route('portal.privacy.exports.download', $exportFile))
            ->assertForbidden();
    }

    public function test_purge_command_deletes_expired_exports(): void
    {
        $user = $this->makeBeneficiary('purge@example.com');
        $path = 'privacy-exports/expired/file.zip';
        Storage::disk('private_documents')->put($path, 'zip');

        PrivacyExportFile::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'disk' => 'private_documents',
            'path' => $path,
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('privacy:purge-expired-exports')->assertSuccessful();
        Storage::disk('private_documents')->assertMissing($path);
        $this->assertDatabaseHas('privacy_export_files', [
            'path' => '',
            'status' => PrivacyExportFileStatus::Deleted->value,
        ]);
    }

    public function test_purge_dry_run_does_not_delete(): void
    {
        $user = $this->makeBeneficiary('dry@example.com');
        $path = 'privacy-exports/dry/file.zip';
        Storage::disk('private_documents')->put($path, 'zip');

        PrivacyExportFile::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'disk' => 'private_documents',
            'path' => $path,
            'format' => 'zip',
            'status' => PrivacyExportFileStatus::Ready,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('privacy:purge-expired-exports', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('private_documents')->assertExists($path);
    }

    public function test_generation_stops_for_anonymized_account(): void
    {
        $beneficiary = $this->makeBeneficiary('anon-export@example.com');
        $officer = $this->makeOfficer(['privacy_requests.approve', 'privacy_requests.export.generate']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataExport($beneficiary, $httpRequest, 'SecretPass1!');
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);
        app(PersonalDataExportService::class)->dispatchGeneration($privacyRequest->fresh(), $officer);

        $beneficiary->forceFill(['account_status' => AccountStatus::Anonymized])->save();

        app(PersonalDataExportService::class)->generateForRequest($privacyRequest->id);

        $this->assertDatabaseHas('privacy_export_files', [
            'privacy_request_id' => $privacyRequest->id,
            'status' => PrivacyExportFileStatus::Failed->value,
        ]);
    }

    private function clearPrivacyRateLimit(User $user): void
    {
        RateLimiter::clear(md5('privacy-request'.$user->id));
    }

    private function requestWithSession(): Request
    {
        $this->startSession();
        $session = app('session.store');
        $session->start();
        $request = Request::create('/', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }

    private function makeBeneficiary(string $email): User
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'password' => Hash::make('SecretPass1!'),
            'account_status' => AccountStatus::Active,
        ]);
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id, 'birth_date' => '1995-01-01']);

        return $user->fresh(['profile']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeOfficer(array $permissions): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'account_status' => AccountStatus::Active,
        ]);
        $user->syncPermissions($permissions);

        return $user;
    }
}
