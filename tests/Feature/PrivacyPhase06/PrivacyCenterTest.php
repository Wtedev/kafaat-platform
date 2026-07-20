<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\IdentityType;
use App\Enums\PrivacyCorrectionFieldCode;
use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\Certificate;
use App\Models\PrivacyCorrectionPayload;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\PrivacyRequest;
use App\Models\Profile;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyCorrectionService;
use App\Services\Privacy\PrivacyRequestService;
use Database\Seeders\RetentionPolicySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PrivacyCenterTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use GeneratesTestIdentityData;
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
    }

    public function test_guest_cannot_access_privacy_center(): void
    {
        $this->get(route('portal.privacy'))->assertRedirect(route('login'));
    }

    public function test_user_without_otp_is_redirected_from_privacy_center(): void
    {
        $user = $this->makeBeneficiary('no-otp@example.com');

        $this->actingAs($user)
            ->get(route('portal.privacy'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_sees_privacy_center_with_masked_identity(): void
    {
        $identity = $this->generateValidNationalId();
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        $user = $this->makeBeneficiary('privacy-center@example.com');
        $user->forceFill([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => $payload['identity_confirmed_at'],
        ])->save();

        $policy = $this->seedActivePrivacyPolicy();

        PrivacyPolicyAcknowledgement::query()->create([
            'user_id' => $user->id,
            'privacy_policy_version_id' => $policy->id,
            'acknowledgement_text_snapshot' => 'أقر بأنني اطلعت على سياسة الخصوصية.',
            'policy_content_hash' => $policy->content_hash,
            'acknowledged_at' => now(),
            'source' => PrivacyPolicyAcknowledgementSource::Registration,
        ]);

        $response = $this->actingAsOtpVerified($user)->get(route('portal.privacy'));

        $response->assertOk();
        $response->assertSee('الخصوصية والبيانات', false);
        $response->assertSee('******'.$payload['identity_number_last4'], false);
        $response->assertDontSee($identity, false);
        $response->assertDontSee('identity_number_ciphertext', false);
        $response->assertDontSee('identity_number_lookup_hash', false);
    }

    public function test_anonymized_user_cannot_access_privacy_center(): void
    {
        $user = $this->makeBeneficiary('anon@example.com');
        $user->forceFill(['account_status' => AccountStatus::Anonymized, 'is_active' => false])->save();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.privacy'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_own_privacy_requests(): void
    {
        $owner = $this->makeBeneficiary('owner@example.com');
        $other = $this->makeBeneficiary('other@example.com');

        $ownRequest = PrivacyRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'request_type' => PrivacyRequestType::DataAccess,
            'status' => PrivacyRequestStatus::Submitted,
            'due_at' => now()->addDays(30),
        ]);

        PrivacyRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $other->id,
            'request_type' => PrivacyRequestType::DataAccess,
            'status' => PrivacyRequestStatus::Submitted,
            'due_at' => now()->addDays(30),
        ]);

        $response = $this->actingAsOtpVerified($owner)->get(route('portal.privacy'));

        $response->assertOk();
        $response->assertSee($ownRequest->uuid, false);
    }

    public function test_user_cannot_cancel_another_users_request(): void
    {
        $owner = $this->makeBeneficiary('cancel-owner@example.com');
        $intruder = $this->makeBeneficiary('cancel-intruder@example.com');

        $request = PrivacyRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'request_type' => PrivacyRequestType::DataAccess,
            'status' => PrivacyRequestStatus::Submitted,
            'due_at' => now()->addDays(30),
        ]);

        $this->actingAsOtpVerified($intruder)
            ->post(route('portal.privacy.requests.cancel', $request))
            ->assertForbidden();
    }

    public function test_access_request_creation_logs_audit_and_activity(): void
    {
        $user = $this->makeBeneficiary('access@example.com');
        $this->clearPrivacyRateLimit($user);

        $response = $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.access'));

        $response->assertRedirect(route('portal.privacy'));
        $response->assertSessionHas('success');

        $privacyRequest = PrivacyRequest::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($privacyRequest);
        $this->assertSame(PrivacyRequestType::DataAccess, $privacyRequest->request_type);
        $response->assertSessionHas('success', fn (string $message): bool => str_contains($message, $privacyRequest->uuid));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'privacy_request.created',
            'result' => AuditLogResult::Success->value,
            'actor_id' => $user->id,
            'target_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'action' => 'privacy_access_requested',
        ]);
    }

    public function test_duplicate_active_access_request_is_blocked(): void
    {
        $user = $this->makeBeneficiary('dup-access@example.com');
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)->post(route('portal.privacy.requests.access'))->assertRedirect();
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.access'))
            ->assertRedirect(route('portal.privacy'))
            ->assertSessionHas('error');
    }

    public function test_access_request_completion_exposes_structured_response_without_internals(): void
    {
        $beneficiary = $this->makeBeneficiary('access-complete@example.com');
        $officer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataAccess($beneficiary, $httpRequest);
        app(PrivacyRequestService::class)->startReview($privacyRequest, $officer);
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);

        $completed = app(PrivacyRequestService::class)->completeAccessRequest($privacyRequest->fresh(), $officer);

        $this->assertSame(PrivacyRequestStatus::Completed, $completed->status);
        $this->assertNotNull($completed->access_response);
        $this->assertNotNull($completed->user_visible_response);

        $encoded = json_encode($completed->access_response);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('audit_log', $encoded);
        $this->assertStringNotContainsString('security_log', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
    }

    public function test_unknown_correction_field_is_rejected(): void
    {
        $user = $this->makeBeneficiary('bad-field@example.com');
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.correction'), [
                'field_code' => 'phone_number',
                'reason' => 'سبب اختبار غير صالح للحقل',
            ])
            ->assertSessionHasErrors('field_code');
    }

    public function test_sensitive_correction_requires_password(): void
    {
        $user = $this->makeBeneficiary('needs-pass@example.com');
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.privacy.requests.correction'), [
                'field_code' => PrivacyCorrectionFieldCode::Email->value,
                'reason' => 'أحتاج تحديث بريدي الإلكتروني الرسمي',
                'email' => 'newmail@example.com',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_identity_correction_does_not_store_plaintext_in_request_details(): void
    {
        $user = $this->makeBeneficiary('identity-corr@example.com');
        $existingIdentity = $this->generateValidNationalId();
        $existingPayload = IdentityNumberService::prepareStoragePayload($existingIdentity, IdentityType::NationalId);
        $user->forceFill([
            'identity_type' => $existingPayload['identity_type']->value,
            'identity_number_ciphertext' => $existingPayload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $existingPayload['identity_number_lookup_hash'],
            'identity_number_last4' => $existingPayload['identity_number_last4'],
            'identity_confirmed_at' => $existingPayload['identity_confirmed_at'],
        ])->save();

        $newIdentity = $this->generateValidNationalId();
        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)->post(route('portal.privacy.requests.correction'), [
            'field_code' => PrivacyCorrectionFieldCode::IdentityNumber->value,
            'reason' => 'رقم الهوية مسجل بخطأ في النظام',
            'identity_type' => IdentityType::NationalId->value,
            'identity_number' => $newIdentity,
            'password' => 'SecretPass1!',
        ])->assertRedirect(route('portal.privacy'));

        $privacyRequest = PrivacyRequest::query()->where('user_id', $user->id)->firstOrFail();
        $details = json_encode($privacyRequest->request_details);
        $this->assertIsString($details);
        $this->assertStringNotContainsString($newIdentity, $details);

        $payload = PrivacyCorrectionPayload::query()->where('privacy_request_id', $privacyRequest->id)->first();
        $this->assertNotNull($payload);
        $this->assertNotSame($newIdentity, $payload->encrypted_value);
    }

    public function test_duplicate_identity_correction_is_rejected(): void
    {
        $identity = $this->generateValidNationalId();
        $storage = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        User::factory()->create([
            'email' => 'existing-id@example.com',
            'role_type' => 'beneficiary',
            'identity_type' => $storage['identity_type']->value,
            'identity_number_ciphertext' => $storage['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $storage['identity_number_lookup_hash'],
            'identity_number_last4' => $storage['identity_number_last4'],
            'identity_confirmed_at' => $storage['identity_confirmed_at'],
        ]);

        $user = $this->makeBeneficiary('dup-id-corr@example.com');
        $ownIdentity = $this->generateValidNationalId();
        $ownStorage = IdentityNumberService::prepareStoragePayload($ownIdentity, IdentityType::NationalId);
        $user->forceFill([
            'identity_type' => $ownStorage['identity_type']->value,
            'identity_number_ciphertext' => $ownStorage['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $ownStorage['identity_number_lookup_hash'],
            'identity_number_last4' => $ownStorage['identity_number_last4'],
            'identity_confirmed_at' => $ownStorage['identity_confirmed_at'],
        ])->save();

        $this->clearPrivacyRateLimit($user);

        $this->actingAsOtpVerified($user)->post(route('portal.privacy.requests.correction'), [
            'field_code' => PrivacyCorrectionFieldCode::IdentityNumber->value,
            'reason' => 'أحتاج تصحيح رقم الهوية المسجل',
            'identity_type' => IdentityType::NationalId->value,
            'identity_number' => $identity,
            'password' => 'SecretPass1!',
        ])->assertSessionHasErrors(['identity_number' => IdentityNumberService::DUPLICATE_MESSAGE]);
    }

    public function test_correction_apply_updates_user_without_modifying_certificates(): void
    {
        $beneficiary = $this->makeBeneficiary('cert-corr@example.com');
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج',
            'slug' => 'cert-corr-program',
            'program_kind' => 'course',
            'status' => 'published',
        ]);

        $certificate = Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-CORR-001',
            'verification_code' => 'VERIFY-CORR-001',
            'issued_at' => now(),
        ]);

        $executor = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve', 'privacy_requests.correction.execute']);
        $httpRequest = $this->requestWithSession();

        $privacyRequest = app(PrivacyRequestService::class)->submitDataCorrection(
            $beneficiary,
            PrivacyCorrectionFieldCode::StructuredName,
            'الاسم مسجل بخطأ بعد إصدار الشهادة',
            [
                'first_name' => 'سعد',
                'father_name' => 'عبدالله',
                'grandfather_name' => 'محمد',
                'family_name' => 'القحطاني',
            ],
            $httpRequest,
        );

        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $executor);
        app(PrivacyRequestService::class)->applyCorrection($privacyRequest->fresh(['user', 'correctionPayload']), $executor);

        $beneficiary->refresh();
        $this->assertSame('سعد عبدالله محمد القحطاني', $beneficiary->fullName());
        $this->assertDatabaseHas('certificates', [
            'id' => $certificate->id,
            'certificate_number' => 'CERT-CORR-001',
        ]);
    }

    public function test_unauthorized_officer_cannot_apply_correction(): void
    {
        $beneficiary = $this->makeBeneficiary('unauth-corr@example.com');
        $program = TrainingProgram::query()->create([
            'title' => 'برنامج',
            'slug' => 'unauth-corr-program',
            'program_kind' => 'course',
            'status' => 'published',
        ]);
        Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-UNAUTH-001',
            'verification_code' => 'VERIFY-UNAUTH-001',
            'issued_at' => now(),
        ]);

        $reviewer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataCorrection(
            $beneficiary,
            PrivacyCorrectionFieldCode::StructuredName,
            'الاسم مسجل بخطأ بعد إصدار الشهادة',
            [
                'first_name' => 'فهد',
                'father_name' => 'سعود',
                'grandfather_name' => 'محمد',
                'family_name' => 'العتيبي',
            ],
            $httpRequest,
        );

        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $reviewer);

        $this->expectException(AuthorizationException::class);
        app(PrivacyCorrectionService::class)->apply($privacyRequest->fresh(['correctionPayload', 'user']), $reviewer);
    }

    public function test_sensitive_correction_payload_is_consumed_after_apply(): void
    {
        $beneficiary = $this->makeBeneficiary('consume@example.com');
        $executor = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve', 'privacy_requests.correction.execute']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitDataCorrection(
            $beneficiary,
            PrivacyCorrectionFieldCode::Email,
            'أحتاج تحديث البريد الرسمي للحساب',
            ['email' => 'updated@example.com', 'password' => 'SecretPass1!'],
            $httpRequest,
            'SecretPass1!',
        );

        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $executor);
        app(PrivacyRequestService::class)->applyCorrection($privacyRequest->fresh(['correctionPayload', 'user']), $executor);

        $payload = PrivacyCorrectionPayload::query()->where('privacy_request_id', $privacyRequest->id)->first();
        $this->assertNotNull($payload?->consumed_at);
    }

    public function test_account_deletion_still_works_from_privacy_workflow(): void
    {
        $beneficiary = $this->makeBeneficiary('deletion-reg@example.com', 'SecretPass1!');
        $this->clearPrivacyRateLimit($beneficiary);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, 'سبب', $httpRequest);

        $this->assertSame(PrivacyRequestType::AccountDeletion, $privacyRequest->request_type);
        $this->assertSame(PrivacyRequestStatus::IdentityVerificationRequired, $privacyRequest->status);
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

    private function makeBeneficiary(string $email, string $password = 'SecretPass1!'): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'password' => Hash::make($password),
            'account_status' => AccountStatus::Active,
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'علي',
            'family_name' => 'السعود',
        ]);
        $user->assignRole('beneficiary');
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
