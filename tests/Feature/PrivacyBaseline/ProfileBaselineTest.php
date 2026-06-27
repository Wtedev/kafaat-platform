<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProfileBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
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
            ->get(route('portal.profile'))
            ->assertOk()
            ->assertSee($user->name);
    }

    public function test_owner_can_update_current_profile_fields(): void
    {
        Storage::fake('public');

        $user = $this->makePortalUserWithProfile([
            'name' => 'الاسم القديم',
            'phone' => '0500000000',
        ], [
            'city' => 'الرياض',
            'job_title' => 'محلل',
        ]);

        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            'name' => 'الاسم الجديد',
            'phone' => '0555555555',
            'city' => 'جدة',
            'job_title' => 'مطور',
            'avatar' => $avatar,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $user->load('profile');

        $this->assertSame('الاسم الجديد', $user->name);
        $this->assertSame('0555555555', $user->phone);
        $this->assertSame('جدة', $user->profile->city);
        $this->assertSame('مطور', $user->profile->job_title);
        $this->assertNotNull($user->profile->avatar);
        $this->assertTrue(Storage::disk('public')->exists($user->profile->avatar));
    }

    public function test_profile_update_only_affects_authenticated_user(): void
    {
        $owner = $this->makePortalUserWithProfile(['name' => 'المالك']);
        $other = $this->makePortalUserWithProfile(['name' => 'مستخدم آخر', 'email' => 'other@example.com']);

        $this->actingAsOtpVerified($other)->patch(route('portal.profile.update'), [
            'name' => 'تعديل من مستخدم آخر',
            'phone' => '0501111111',
            'city' => 'الدمام',
            'job_title' => null,
        ])->assertRedirect();

        $this->assertSame('المالك', $owner->fresh()->name);
        $this->assertSame('تعديل من مستخدم آخر', $other->fresh()->name);
    }

    public function test_profile_update_rejects_invalid_phone_length(): void
    {
        $user = $this->makePortalUserWithProfile();

        $this->actingAsOtpVerified($user)->patch(route('portal.profile.update'), [
            'name' => $user->name,
            'phone' => str_repeat('1', 31),
            'city' => null,
            'job_title' => null,
        ])->assertSessionHasErrors('phone');
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    private function makePortalUserWithProfile(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $userAttributes));
        $user->assignRole('trainee');
        Profile::query()->create(array_merge(['user_id' => $user->id], $profileAttributes));

        return $user->fresh(['profile']);
    }
}
