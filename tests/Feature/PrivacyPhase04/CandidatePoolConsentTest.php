<?php

namespace Tests\Feature\PrivacyPhase04;

use App\Enums\CandidatePoolConsentEventType;
use App\Enums\CandidatePoolPreferenceStatus;
use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\CandidatePoolConsentVersion;
use App\Models\Profile;
use App\Models\User;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolQuery;
use App\Enums\IdentityType;
use Database\Seeders\CandidatePoolConsentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\CreatesValidPdfUpload;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class CandidatePoolConsentTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use CreatesValidPdfUpload;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->seed(CandidatePoolConsentSeeder::class);
        Storage::fake('private_documents');
    }

    public function test_should_prompt_for_user_with_complete_identity(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->assertTrue(app(CandidatePoolConsentService::class)->shouldPrompt($user));
    }

    public function test_prompted_endpoint_is_idempotent(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)
            ->postJson(route('portal.candidate-pool.prompted'))
            ->assertNoContent();
        $this->actingAsOtpVerified($user)
            ->postJson(route('portal.candidate-pool.prompted'))
            ->assertNoContent();

        $this->assertSame(1, $user->candidatePoolConsentEvents()->where('event_type', CandidatePoolConsentEventType::Prompted)->count());
        $this->assertFalse(app(CandidatePoolConsentService::class)->shouldPrompt($user->fresh()));
    }

    public function test_grant_creates_granted_preference_and_audit(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.grant'))->assertRedirect();

        $preference = $user->fresh()->candidatePoolPreference;
        $this->assertSame(CandidatePoolPreferenceStatus::Granted, $preference->current_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate_pool.granted', 'target_user_id' => $user->id]);
    }

    public function test_decline_does_not_block_portal_access(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.decline'))->assertRedirect();
        $this->actingAsOtpVerified($user)->get(route('portal.dashboard'))->assertOk();

        $this->assertSame(CandidatePoolPreferenceStatus::Declined, $user->fresh()->candidatePoolPreference->current_status);
    }

    public function test_granted_user_with_cv_appears_in_candidate_pool(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.grant'));
        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), [
            'cv' => $this->validPdfUpload(),
        ]);

        $this->assertTrue(app(CandidatePoolQuery::class)->eligibleQuery()->where('users.id', $user->id)->exists());
    }

    public function test_granted_user_without_cv_does_not_appear_in_pool(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.grant'));

        $this->assertFalse(app(CandidatePoolQuery::class)->eligibleQuery()->where('users.id', $user->id)->exists());
    }

    public function test_withdrawn_user_does_not_appear_even_with_cv(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.grant'));
        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), ['cv' => $this->validPdfUpload()]);
        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.settings.withdraw'));

        $this->assertFalse(app(CandidatePoolQuery::class)->eligibleQuery()->where('users.id', $user->id)->exists());
    }

    public function test_reconsent_version_hides_users_until_regranted(): void
    {
        $user = $this->makeIdentifiedUser();

        $this->actingAsOtpVerified($user)->post(route('portal.candidate-pool.grant'));
        $this->actingAsOtpVerified($user)->post(route('portal.competency.cv.store'), ['cv' => $this->validPdfUpload()]);

        $oldVersionId = CandidatePoolConsentVersion::query()->where('version', '1.0')->value('id');

        CandidatePoolConsentVersion::query()->create([
            'version' => '2.0',
            'title' => 'إصدار 2',
            'content' => '<p>نص محدّث</p>',
            'content_hash' => hash('sha256', 'v2'),
            'status' => PrivacyPolicyVersionStatus::Active,
            'requires_reconsent' => true,
            'effective_at' => now(),
            'published_at' => now(),
        ]);

        CandidatePoolConsentVersion::query()->whereKey($oldVersionId)->update(['status' => PrivacyPolicyVersionStatus::Archived]);
        \App\Services\CandidatePool\CandidatePoolConsentVersionService::forgetCache();

        $this->assertFalse(app(CandidatePoolQuery::class)->eligibleQuery()->where('users.id', $user->id)->exists());
        $this->assertTrue(app(CandidatePoolConsentService::class)->shouldPrompt($user->fresh()));
    }

    private function makeIdentifiedUser(): User
    {
        $identity = $this->generateValidNationalId();

        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'علي',
            'family_name' => 'السعود',
            'phone' => '0500000000',
            'identity_type' => IdentityType::NationalId,
            'identity_number_lookup_hash' => hash('sha256', $identity),
            'identity_number_last4' => substr($identity, -4),
        ]);
        $user->assignRole('trainee');
        Profile::query()->create(['user_id' => $user->id, 'birth_date' => '1995-01-01']);

        return $user->fresh(['profile']);
    }
}
