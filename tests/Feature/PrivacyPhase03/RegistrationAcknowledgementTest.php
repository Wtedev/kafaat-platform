<?php

namespace Tests\Feature\PrivacyPhase03;

use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\PrivacyPolicyVersion;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Privacy\PrivacyPolicyPublisher;
use App\Services\Privacy\PrivacyPolicyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\TestCase;

class RegistrationAcknowledgementTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedActivePrivacyPolicy();
    }

    public function test_register_form_shows_active_policy_version(): void
    {
        $policy = PrivacyPolicyService::activeOrFail();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee($policy->version)
            ->assertSee('أقر بأنني اطلعت على سياسة الخصوصية');
    }

    public function test_registration_fails_without_acknowledgement(): void
    {
        $payload = $this->validRegistrationPayload();
        unset($payload['privacy_policy_acknowledged']);

        $response = $this->post(route('register'), $payload);

        $response->assertSessionHasErrors('privacy_policy_acknowledged');
        $this->assertSame(0, User::query()->count());
    }

    public function test_registration_creates_acknowledgement_record(): void
    {
        Notification::fake();

        $policy = PrivacyPolicyService::activeOrFail();
        $payload = $this->validRegistrationPayload(['email' => 'ack-user@example.com']);

        $this->post(route('register'), $payload)->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'ack-user@example.com')->firstOrFail();

        $this->assertDatabaseHas('privacy_policy_acknowledgements', [
            'user_id' => $user->id,
            'privacy_policy_version_id' => $policy->id,
            'policy_content_hash' => $policy->content_hash,
            'source' => PrivacyPolicyAcknowledgementSource::Registration->value,
        ]);

        $ack = PrivacyPolicyAcknowledgement::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('أقر بأنني اطلعت على سياسة الخصوصية.', $ack->acknowledgement_text_snapshot);

        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_registration_rejects_stale_policy_version(): void
    {
        $policy = PrivacyPolicyService::activeOrFail();
        $payload = $this->validRegistrationPayload([
            'privacy_policy_version' => '0.9',
            'privacy_policy_acknowledged' => '1',
        ]);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('privacy_policy_version');

        $this->assertSame(0, User::query()->count());
    }

    public function test_registration_unavailable_without_active_policy(): void
    {
        PrivacyPolicyVersion::query()->delete();
        PrivacyPolicyService::forgetCache();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('التسجيل غير متاح مؤقتاً');
    }

    public function test_policy_version_change_between_get_and_post_is_rejected(): void
    {
        $payload = $this->validRegistrationPayload([
            'privacy_policy_version' => PrivacyPolicyService::activeOrFail()->version,
            'privacy_policy_acknowledged' => '1',
        ]);

        $draft = PrivacyPolicyVersion::factory()->create(['version' => '9.9']);
        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('privacy_policy_version');
    }
}
