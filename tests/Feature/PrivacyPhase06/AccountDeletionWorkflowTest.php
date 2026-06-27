<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\AccountStatus;
use App\Enums\DataDeletionPlanStatus;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\Certificate;
use App\Models\DataDeletionPlan;
use App\Models\PrivacyRequest;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Privacy\DataDeletionPlanService;
use App\Services\Privacy\PersonalDataDeletionService;
use App\Services\Privacy\PrivacyRequestService;
use Database\Seeders\RetentionPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class AccountDeletionWorkflowTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        $this->seed(RetentionPolicySeeder::class);
    }

    public function test_full_account_anonymization_workflow_preserves_restricted_records(): void
    {
        $beneficiary = $this->makeBeneficiary('delete-flow@example.com', 'SecretPass1!');
        $originalUserId = $beneficiary->id;
        $originalEmail = $beneficiary->email;

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج اختبار',
            'slug' => 'test-program-deletion',
            'program_kind' => 'course',
            'status' => 'published',
        ]);
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $beneficiary->id,
            'status' => 'approved',
        ]);

        Certificate::query()->create([
            'user_id' => $beneficiary->id,
            'certificateable_type' => TrainingProgram::class,
            'certificateable_id' => $program->id,
            'certificate_number' => 'CERT-TEST-001',
            'verification_code' => 'VERIFY-001',
            'issued_at' => now(),
        ]);

        $officer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);
        $executor = $this->makeOfficer(['privacy_requests.execute'], 'ExecPass1!');

        $httpRequest = $this->requestWithSession();

        $privacyRequest = app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, 'سبب اختبار', $httpRequest);
        app(PrivacyRequestService::class)->verifyIdentityWithPassword($privacyRequest, $beneficiary, 'SecretPass1!', $httpRequest);
        app(PrivacyRequestService::class)->startReview($privacyRequest, $officer);
        app(PrivacyRequestService::class)->approve($privacyRequest, $officer);

        $plan = app(DataDeletionPlanService::class)->createDraft($privacyRequest->fresh(), $officer);
        app(DataDeletionPlanService::class)->approve($plan, $officer);

        $executorRequest = $this->requestWithSession();
        SensitiveAccessVerification::markVerified($executorRequest);

        app(PersonalDataDeletionService::class)->executeApprovedPlan(
            $privacyRequest->fresh(['deletionPlan']),
            $plan->fresh(),
            $executor,
            $executorRequest,
        );

        $anonymized = User::query()->find($originalUserId);

        $this->assertNotNull($anonymized);
        $this->assertSame(AccountStatus::Anonymized, $anonymized->account_status);
        $this->assertFalse($anonymized->is_active);
        $this->assertNotSame($originalEmail, $anonymized->email);
        $this->assertStringContainsString('@invalid.local', $anonymized->email);
        $this->assertNull($anonymized->phone);
        $this->assertNull($anonymized->identity_number_lookup_hash);
        $this->assertNotNull($anonymized->privacy_deleted_at);
        $this->assertNotNull($anonymized->anonymized_at);

        $this->assertDatabaseHas('program_registrations', ['user_id' => $originalUserId]);
        $this->assertDatabaseHas('certificates', ['user_id' => $originalUserId]);
        $this->assertDatabaseMissing('users', ['id' => $originalUserId, 'email' => $originalEmail]);

        $this->assertDatabaseHas('privacy_requests', [
            'id' => $privacyRequest->id,
            'status' => PrivacyRequestStatus::Completed->value,
        ]);
    }

    public function test_execute_requires_approved_plan_and_execute_permission(): void
    {
        $beneficiary = $this->makeBeneficiary('needs-plan@example.com');
        $reviewer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);
        $unauthorized = $this->makeOfficer(['privacy_requests.review']);

        $httpRequest = $this->requestWithSession();

        $privacyRequest = app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, null, $httpRequest);
        app(PrivacyRequestService::class)->verifyIdentityWithPassword($privacyRequest, $beneficiary, 'SecretPass1!', $httpRequest);
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $reviewer);

        $executorRequest = $this->requestWithSession();
        SensitiveAccessVerification::markVerified($executorRequest);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(PersonalDataDeletionService::class)->executeApprovedPlan(
            $privacyRequest->fresh(),
            DataDeletionPlan::query()->create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'privacy_request_id' => $privacyRequest->id,
                'user_id' => $beneficiary->id,
                'status' => DataDeletionPlanStatus::Approved,
                'plan_snapshot' => [],
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
            ]),
            $unauthorized,
            $executorRequest,
        );
    }

    public function test_user_query_bulk_delete_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\UserDeletionNotAllowedException::class);
        User::query()->where('email', 'like', '%@example.com')->delete();
    }

    public function test_anonymized_user_cannot_login(): void
    {
        $user = $this->makeBeneficiary('blocked-login@example.com');
        $user->forceFill([
            'account_status' => AccountStatus::Anonymized,
            'is_active' => false,
        ])->save();

        $response = $this->post(route('login'), [
            'email' => 'blocked-login@example.com',
            'password' => 'SecretPass1!',
        ]);

        $response->assertSessionHasErrors('email');
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

    /**
     * @param  list<string>  $permissions
     */
    private function makeOfficer(array $permissions, string $password = 'password'): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'account_status' => AccountStatus::Active,
        ]);
        $user->assignRole('privacy_officer');
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function makeBeneficiary(string $email, string $password = 'SecretPass1!'): User
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'password' => Hash::make($password),
            'account_status' => AccountStatus::Active,
        ]);
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh();
    }
}
