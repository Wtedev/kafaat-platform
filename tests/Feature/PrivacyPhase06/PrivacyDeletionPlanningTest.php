<?php

namespace Tests\Feature\PrivacyPhase06;

use App\Enums\DataDeletionPlanStatus;
use App\Enums\DeletionHandlerName;
use App\Enums\PrivacyRequestStatus;
use App\Models\Profile;
use App\Models\User;
use App\Services\Privacy\DataDeletionPlanService;
use App\Services\Privacy\PrivacyRequestService;
use Database\Seeders\RetentionPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PrivacyDeletionPlanningTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        $this->seed(RetentionPolicySeeder::class);
    }

    public function test_duplicate_active_account_deletion_request_is_rejected(): void
    {
        $beneficiary = $this->makeBeneficiary('duplicate@example.com');

        app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, null, $this->requestWithSession());

        $this->expectException(\InvalidArgumentException::class);
        app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary->fresh(), null, $this->requestWithSession());
    }

    public function test_deletion_plan_is_created_from_approved_request(): void
    {
        $beneficiary = $this->makeBeneficiary('plan-create@example.com');
        $officer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, null, $httpRequest);
        app(PrivacyRequestService::class)->verifyIdentityWithPassword($privacyRequest, $beneficiary, 'SecretPass1!', $httpRequest);
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);

        $plan = app(DataDeletionPlanService::class)->createDraft($privacyRequest->fresh(), $officer);

        $this->assertSame(DataDeletionPlanStatus::ReadyForReview, $plan->status);
        $this->assertNotEmpty($plan->plan_snapshot['resources'] ?? []);
        $this->assertCount(
            count(DeletionHandlerName::executionOrder()),
            $plan->steps,
        );
    }

    public function test_deletion_plan_requires_approval_before_execution_status(): void
    {
        $beneficiary = $this->makeBeneficiary('plan-approve@example.com');
        $officer = $this->makeOfficer(['privacy_requests.review', 'privacy_requests.approve']);

        $httpRequest = $this->requestWithSession();
        $privacyRequest = app(PrivacyRequestService::class)->submitAccountDeletion($beneficiary, null, $httpRequest);
        app(PrivacyRequestService::class)->verifyIdentityWithPassword($privacyRequest, $beneficiary, 'SecretPass1!', $httpRequest);
        app(PrivacyRequestService::class)->approve($privacyRequest->fresh(), $officer);

        $plan = app(DataDeletionPlanService::class)->createDraft($privacyRequest->fresh(), $officer);
        app(DataDeletionPlanService::class)->approve($plan, $officer);

        $this->assertSame(DataDeletionPlanStatus::Approved, $plan->fresh()->status);
        $this->assertSame(PrivacyRequestStatus::Approved, $privacyRequest->fresh()->status);
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
    private function makeOfficer(array $permissions): User
    {
        $user = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('staff');
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function makeBeneficiary(string $email): User
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'email' => $email,
            'password' => Hash::make('SecretPass1!'),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user->fresh();
    }
}
