<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class LoginBaselineTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_email_and_password_and_is_redirected_to_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'trainee',
            'is_active' => true,
        ]);
        $user->assignRole('trainee');

        $response = $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'CorrectPass1!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'WrongPass1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_is_rejected_and_logged_out(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => false,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'inactive@example.com',
            'password' => 'CorrectPass1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_portal_routes_require_otp_verification_in_session(): void
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
        ]);
        $user->assignRole('trainee');

        $this->actingAs($user)
            ->get(route('portal.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_portal_dashboard_is_accessible_after_otp_session_flag(): void
    {
        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
        ]);
        $user->assignRole('trainee');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.dashboard'))
            ->assertOk();
    }
}
