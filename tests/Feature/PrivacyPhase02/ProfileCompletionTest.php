<?php

namespace Tests\Feature\PrivacyPhase02;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    public function test_legacy_user_can_complete_profile_without_auto_splitting_name(): void
    {
        $user = User::factory()->create([
            'name' => 'اسم قديم واحد',
            'role_type' => 'beneficiary',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        $this->assertNull($user->first_name);

        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation']);

        $this->actingAsOtpVerified($user)
            ->post(route('portal.profile.complete.store'), $payload)
            ->assertRedirect(route('portal.dashboard'));

        $user->refresh();
        $this->assertSame('أحمد', $user->first_name);
        $this->assertTrue($user->hasCompletedRequiredIdentityData());
        $this->assertNotSame('اسم قديم واحد', $user->fullName());
    }

    public function test_incomplete_user_sees_completion_banner(): void
    {
        $user = User::factory()->create([
            'name' => 'مستخدم قديم',
            'role_type' => 'beneficiary',
        ]);
        $user->assignRole('beneficiary');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('بيانات حسابك غير مكتملة');
    }

    public function test_user_json_does_not_expose_identity_secrets(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'identity_number_ciphertext' => 'encrypted-value',
            'identity_number_lookup_hash' => 'hash-value',
            'identity_number_last4' => '1234',
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('identity_number_ciphertext', $array);
        $this->assertArrayNotHasKey('identity_number_lookup_hash', $array);
    }
}
