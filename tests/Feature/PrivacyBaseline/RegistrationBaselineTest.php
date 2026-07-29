<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\SignupEmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CompletesSignupViaOtp;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RegistrationBaselineTest extends TestCase
{
    use CompletesSignupViaOtp;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('إنشاء حساب جديد')
            ->assertDontSee('لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.');
    }

    public function test_user_can_register_with_current_fields_and_profile_is_created(): void
    {
        $payload = $this->validRegistrationPayload([
            'email' => 'new-beneficiary@example.com',
        ]);

        $user = $this->registerAndVerifyOtp($payload);

        $this->assertSame('beneficiary', $user->role_type);
        $this->assertTrue($user->hasRole('beneficiary'));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('SecurePass1!', $user->password));
        $this->assertTrue($user->hasStructuredName());
        $this->assertTrue($user->hasIdentityOnRecord());
    }

    public function test_register_does_not_create_user_before_otp(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'email' => 'pending-only@example.com',
        ]);

        $this->post(route('register'), $payload)
            ->assertRedirect(route('register.verify.show'));

        $this->assertSame(0, User::query()->where('email', 'pending-only@example.com')->count());
        $this->assertDatabaseHas('pending_registrations', ['email' => 'pending-only@example.com']);
        Notification::assertSentOnDemand(SignupEmailVerificationCode::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $payload = $this->validRegistrationPayload(['email' => 'dup@example.com']);

        $response = $this->post(route('register'), $payload);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', 'dup@example.com')->count());
        $this->assertSame(0, PendingRegistration::query()->where('email', 'dup@example.com')->count());
    }

    public function test_registration_validation_requires_core_fields(): void
    {
        $response = $this->post(route('register'), []);

        $response->assertSessionHasErrors([
            'first_name',
            'email',
            'password',
            'identity_number',
            'birth_date',
            'phone',
        ]);
    }
}
