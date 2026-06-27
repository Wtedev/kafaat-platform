<?php

namespace Tests\Feature\PrivacyPhase03;

use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\PrivacyPolicyVersion;
use App\Models\User;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use App\Services\Privacy\PrivacyPolicyPublisher;
use App\Services\Privacy\PrivacyPolicyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\TestCase;

class ExistingUserReacknowledgementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedActivePrivacyPolicy();
    }

    public function test_existing_user_without_acknowledgement_is_not_forced_when_reack_not_required(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->assignRole('trainee');

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('portal.dashboard'))
            ->assertOk();
    }

    public function test_user_is_redirected_when_reacknowledgement_required(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->assignRole('trainee');

        $draft = PrivacyPolicyVersion::factory()->create([
            'version' => '2.0',
            'requires_reacknowledgement' => true,
        ]);
        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('portal.privacy-policy.acknowledge'));
    }

    public function test_user_can_access_public_privacy_page_while_reack_pending(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->assignRole('trainee');

        $draft = PrivacyPolicyVersion::factory()->create([
            'version' => '2.0',
            'requires_reacknowledgement' => true,
        ]);
        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('public.privacy'))
            ->assertOk();
    }

    public function test_user_can_submit_reacknowledgement_and_access_portal(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->assignRole('trainee');

        $draft = PrivacyPolicyVersion::factory()->create([
            'version' => '2.0',
            'requires_reacknowledgement' => true,
        ]);
        app(PrivacyPolicyPublisher::class)->publish($draft);

        $policy = PrivacyPolicyService::activeOrFail();

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->post(route('portal.privacy-policy.acknowledge.store'), [
                'privacy_policy_version' => $policy->version,
                'privacy_policy_acknowledged' => '1',
            ])
            ->assertRedirect(route('portal.dashboard'));

        $this->assertTrue(app(PrivacyPolicyAcknowledgementService::class)->hasAcknowledgedVersion($user, $policy));

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('portal.dashboard'))
            ->assertOk();
    }

    public function test_acknowledged_user_not_redirected_after_reack(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->assignRole('trainee');

        $draft = PrivacyPolicyVersion::factory()->create([
            'version' => '2.0',
            'requires_reacknowledgement' => true,
        ]);
        app(PrivacyPolicyPublisher::class)->publish($draft);
        $policy = PrivacyPolicyService::activeOrFail();

        PrivacyPolicyAcknowledgement::query()->create([
            'user_id' => $user->id,
            'privacy_policy_version_id' => $policy->id,
            'acknowledgement_text_snapshot' => 'أقر بأنني اطلعت على سياسة الخصوصية.',
            'policy_content_hash' => $policy->content_hash,
            'acknowledged_at' => now(),
            'source' => PrivacyPolicyAcknowledgementSource::PolicyUpdate,
        ]);

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('portal.dashboard'))
            ->assertOk();
    }
}
