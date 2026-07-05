<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProfileBaselineTest extends TestCase
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

    public function test_owner_can_view_profile_page(): void
    {
        $user = $this->makePortalUserWithProfile();

        $this->actingAsOtpVerified($user)
            ->get(route('portal.settings.profile'))
            ->assertOk()
            ->assertSee($user->fullName());
    }

    public function test_owner_can_update_current_profile_fields(): void
    {
        Storage::fake('public');

        $user = $this->makePortalUserWithProfile();
        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation'], $payload['identity_type'], $payload['identity_number']);

        $payload['city'] = 'جدة';
        $payload['job_title'] = 'مطور';

        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            ...$payload,
            'avatar' => $avatar,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $user->load('profile');

        $this->assertSame('جدة', $user->profile->city);
        $this->assertSame('مطور', $user->profile->job_title);
        $this->assertNotNull($user->profile->avatar);
    }

    public function test_profile_update_only_affects_authenticated_user(): void
    {
        $owner = $this->makePortalUserWithProfile(['email' => 'owner@example.com']);
        $other = $this->makePortalUserWithProfile(['email' => 'other@example.com']);

        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation'], $payload['identity_type'], $payload['identity_number']);
        $payload['first_name'] = 'تعديل';

        $this->actingAsOtpVerified($other)->patch(route('portal.profile.update'), $payload)->assertRedirect();

        $this->assertNotSame('تعديل', $owner->fresh()->first_name);
        $this->assertSame('تعديل', $other->fresh()->first_name);
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function makePortalUserWithProfile(array $userAttributes = []): User
    {
        $payload = $this->validRegistrationPayload();
        unset($payload['email'], $payload['password'], $payload['password_confirmation']);

        $user = User::factory()->create(array_merge([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
            'first_name' => $payload['first_name'],
            'father_name' => $payload['father_name'],
            'grandfather_name' => $payload['grandfather_name'],
            'family_name' => $payload['family_name'],
            'name' => 'أحمد محمد عبدالله السعود',
            'phone' => '+966501234567',
        ], $userAttributes));
        $user->assignRole('trainee');
        Profile::query()->create([
            'user_id' => $user->id,
            'birth_date' => $payload['birth_date'],
        ]);

        return $user->fresh(['profile']);
    }
}
