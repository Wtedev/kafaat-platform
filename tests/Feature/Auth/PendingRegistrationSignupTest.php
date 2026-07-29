<?php

namespace Tests\Feature\Auth;

use App\Enums\ProgramStatus;
use App\Models\EmailVerificationCode;
use App\Models\PendingRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\SignupEmailVerificationCode;
use App\Notifications\VerifyEmailCode;
use App\Services\Auth\EmailVerificationCodeService;
use App\Services\Auth\PendingRegistrationService;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CompletesSignupViaOtp;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class PendingRegistrationSignupTest extends TestCase
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

    public function test_register_page_shows_steps_and_ux_copy(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('بيانات الحساب')
            ->assertSee('التحقق من البريد')
            ->assertSee('تم إنشاء الحساب')
            ->assertSee('تأكيد البريد الإلكتروني')
            ->assertSee('لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.');
    }

    public function test_email_confirmation_mismatch_returns_exact_arabic_message(): void
    {
        $payload = $this->validRegistrationPayload([
            'email' => 'one@example.com',
            'email_confirmation' => 'two@example.com',
        ]);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors([
                'email' => 'البريد الإلكتروني وتأكيد البريد الإلكتروني غير متطابقين.',
            ]);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PendingRegistration::query()->count());
    }

    public function test_invalid_email_returns_exact_arabic_message(): void
    {
        $payload = $this->validRegistrationPayload([
            'email' => 'not-an-email',
            'email_confirmation' => 'not-an-email',
        ]);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors([
                'email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            ]);
    }

    public function test_email_in_use_returns_exact_arabic_message_and_login_link(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $payload = $this->validRegistrationPayload(['email' => 'taken@example.com']);

        $this->from(route('register'))
            ->followingRedirects()
            ->post(route('register'), $payload)
            ->assertSee('يوجد حساب مرتبط بهذا البريد الإلكتروني، يمكنك تسجيل الدخول بدلًا من إنشاء حساب جديد.')
            ->assertSee('الانتقال إلى تسجيل الدخول');
    }

    public function test_email_is_normalized_trim_and_lowercase(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'email' => '  Mixed.Case@Example.COM ',
            'email_confirmation' => '  Mixed.Case@Example.COM ',
        ]);

        $this->post(route('register'), $payload)
            ->assertRedirect(route('register.verify.show'));

        $this->assertDatabaseHas('pending_registrations', [
            'email' => 'mixed.case@example.com',
        ]);
        $this->assertSame(0, User::query()->count());
    }

    public function test_case_insensitive_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $payload = $this->validRegistrationPayload(['email' => 'DUP@example.com']);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors('email');

        $this->assertSame(0, PendingRegistration::query()->count());
    }

    public function test_successful_start_stores_hashed_password_and_otp_not_plaintext(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'secure@example.com']);

        $this->post(route('register'), $payload)->assertRedirect(route('register.verify.show'));

        $pending = PendingRegistration::query()->where('email', 'secure@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('SecurePass1!', $pending->password_hash));
        $this->assertNotSame('SecurePass1!', $pending->getRawOriginal('password_hash'));
        $this->assertFalse(Hash::needsRehash($pending->code_hash));

        Notification::assertSentOnDemand(SignupEmailVerificationCode::class, function (SignupEmailVerificationCode $notification) use ($pending): bool {
            return Hash::check($notification->code, $pending->fresh()->code_hash)
                && strlen($notification->code) === 6
                && ! str_contains((string) $pending->getRawOriginal('code_hash'), $notification->code);
        });

        $rawPayload = (string) $pending->getRawOriginal('payload');
        $this->assertNotSame('', $rawPayload);
        $this->assertStringNotContainsString('SecurePass1!', $rawPayload);
        $this->assertIsArray($pending->payload);
        $this->assertArrayNotHasKey('password', $pending->payload);
    }

    public function test_otp_screen_masks_email_and_shows_steps(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'maskme@example.com']);
        $this->post(route('register'), $payload)->assertRedirect(route('register.verify.show'));

        $this->get(route('register.verify.show'))
            ->assertOk()
            ->assertSee('m***@example.com', false)
            ->assertDontSee('maskme@example.com', false)
            ->assertSee('التحقق من البريد')
            ->assertSee('لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.');
    }

    public function test_wrong_otp_returns_exact_arabic_message_and_increments_attempts(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'wrong-otp@example.com']);
        $this->post(route('register'), $payload);

        $this->post(route('register.verify'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'رمز التحقق غير صحيح.']);

        $this->assertSame(1, PendingRegistration::query()->where('email', 'wrong-otp@example.com')->value('attempts'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_expired_otp_returns_exact_arabic_message(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'expired-otp@example.com']);
        $this->post(route('register'), $payload);

        PendingRegistration::query()->where('email', 'expired-otp@example.com')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $code = $this->latestSignupOtpCode('expired-otp@example.com');

        $this->post(route('register.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_too_many_attempts_locks_otp(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'locked@example.com']);
        $this->post(route('register'), $payload);

        for ($i = 0; $i < PendingRegistrationService::MAX_ATTEMPTS; $i++) {
            $this->post(route('register.verify'), ['code' => '000000']);
        }

        $this->post(route('register.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, User::query()->count());
    }

    public function test_resend_invalidates_previous_code_and_respects_cooldown(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'resend@example.com']);
        $this->post(route('register'), $payload);

        $pending = PendingRegistration::query()->where('email', 'resend@example.com')->firstOrFail();
        $oldHash = $pending->code_hash;

        $this->post(route('register.verify.resend'))
            ->assertSessionHasErrors('code');

        $this->assertSame($oldHash, $pending->fresh()->code_hash);

        $pending->forceFill([
            'last_sent_at' => now()->subSeconds(PendingRegistrationService::RESEND_COOLDOWN_SECONDS + 1),
        ])->save();

        $this->post(route('register.verify.resend'))
            ->assertRedirect();

        $fresh = $pending->fresh();
        $this->assertNotSame($oldHash, $fresh->code_hash);
        $this->assertSame(0, $fresh->attempts);
        $this->assertSame(1, $fresh->resend_count);
        $this->assertSame(0, User::query()->count());
    }

    public function test_going_back_invalidates_prior_otp(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'restart@example.com']);
        $this->post(route('register'), $payload);

        $pendingId = PendingRegistration::query()->where('email', 'restart@example.com')->value('id');
        $this->assertNotNull($pendingId);

        $this->get(route('register', ['restart' => 1]))->assertOk();

        $pending = PendingRegistration::query()->find($pendingId);
        $this->assertNotNull($pending->consumed_at);

        $this->get(route('register.verify.show'))
            ->assertRedirect(route('register'));
    }

    public function test_successful_verify_creates_user_profile_role_and_logs_in(): void
    {
        $payload = $this->validRegistrationPayload(['email' => 'success@example.com']);
        $user = $this->registerAndVerifyOtp($payload);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session('otp_verified'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('beneficiary'));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertNotNull(PendingRegistration::query()->where('email', 'success@example.com')->value('consumed_at'));
    }

    public function test_otp_cannot_be_reused_after_success(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'reuse@example.com']);
        $this->post(route('register'), $payload);
        $code = $this->latestSignupOtpCode('reuse@example.com');

        $this->post(route('register.verify'), ['code' => $code])->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'reuse@example.com')->count());

        $this->post(route('logout'));

        $this->post(route('register.verify'), ['code' => $code])
            ->assertRedirect(route('register'));
    }

    public function test_signup_otp_cannot_be_used_as_login_otp(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'cross-a@example.com']);
        $this->post(route('register'), $payload);
        $signupCode = $this->latestSignupOtpCode('cross-a@example.com');

        $user = User::factory()->create([
            'email' => 'login-user@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        $this->post(route('login'), [
            'email' => 'login-user@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->post(route('verification.verify'), ['code' => $signupCode])
            ->assertSessionHasErrors('code');

        $this->assertNotTrue(session('otp_verified'));
    }

    public function test_login_otp_cannot_verify_pending_registration(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'login-otp@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');

        app(EmailVerificationCodeService::class)->sendCode($user);

        $loginCode = null;
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$loginCode): bool {
            $loginCode = $notification->code;

            return true;
        });

        $payload = $this->validRegistrationPayload(['email' => 'cross-b@example.com']);
        $this->post(route('register'), $payload);

        $this->post(route('register.verify'), ['code' => $loginCode])
            ->assertSessionHasErrors(['code' => 'رمز التحقق غير صحيح.']);

        $this->assertSame(0, User::query()->where('email', 'cross-b@example.com')->count());
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_abandoned_pending_leaves_no_user_and_email_reusable(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'abandon@example.com']);
        $this->post(route('register'), $payload);

        PendingRegistration::query()->where('email', 'abandon@example.com')->update([
            'expires_at' => now()->subMinute(),
            'consumed_at' => now(),
        ]);

        $this->assertSame(0, User::query()->where('email', 'abandon@example.com')->count());

        $fresh = $this->validRegistrationPayload(['email' => 'abandon@example.com']);
        $this->post(route('register'), $fresh)
            ->assertRedirect(route('register.verify.show'));

        $this->assertSame(0, User::query()->where('email', 'abandon@example.com')->count());
    }

    public function test_register_with_program_return_redirects_after_otp_without_auto_register(): void
    {
        Notification::fake();

        $program = TrainingProgram::query()->create([
            'title' => 'برنامج signup-return-program',
            'slug' => 'signup-return-program',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);

        $this->get(route('register', ['return' => '/programs/signup-return-program']))
            ->assertOk()
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY, '/programs/signup-return-program');

        $payload = $this->validRegistrationPayload(['email' => 'return-signup@example.com']);
        $this->post(route('register'), $payload)->assertRedirect(route('register.verify.show'));

        $code = $this->latestSignupOtpCode('return-signup@example.com');

        $this->post(route('register.verify'), ['code' => $code])
            ->assertRedirect('/programs/signup-return-program')
            ->assertSessionHas('success', 'تم التحقق من البريد وإنشاء حسابك بنجاح.');

        $user = User::query()->where('email', 'return-signup@example.com')->firstOrFail();
        $this->assertSame(0, ProgramRegistration::query()->where('user_id', $user->id)->count());
        $this->assertTrue(session('otp_verified'));
        $this->assertSame(0, EmailVerificationCode::query()->where('user_id', $user->id)->count());
    }

    public function test_open_redirect_return_url_is_ignored(): void
    {
        Notification::fake();

        $this->get(route('register', ['return' => 'https://evil.example/phish']))->assertOk();
        $this->assertNull(session(SafeLoginReturnUrl::SESSION_KEY));

        $payload = $this->validRegistrationPayload(['email' => 'safe-return@example.com']);
        $this->post(route('register'), $payload);
        $code = $this->latestSignupOtpCode('safe-return@example.com');

        $this->post(route('register.verify'), ['code' => $code])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_success_flash_only_after_user_exists(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'flash@example.com']);
        $this->post(route('register'), $payload)
            ->assertSessionMissing('success');

        $this->assertSame(0, User::query()->where('email', 'flash@example.com')->count());

        $code = $this->latestSignupOtpCode('flash@example.com');
        $this->post(route('register.verify'), ['code' => $code])
            ->assertSessionHas('success', 'تم التحقق من البريد وإنشاء حسابك بنجاح.');

        $this->assertSame(1, User::query()->where('email', 'flash@example.com')->count());
    }

    public function test_notify_email_defaults_remain_false(): void
    {
        $payload = $this->validRegistrationPayload(['email' => 'prefs@example.com']);
        $user = $this->registerAndVerifyOtp($payload);

        $this->assertFalse((bool) $user->notify_email);
    }

    public function test_pending_registration_not_visible_as_admin_user(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'ghost@example.com']);
        $this->post(route('register'), $payload);

        $this->assertDatabaseHas('pending_registrations', ['email' => 'ghost@example.com']);
        $this->assertSame(0, User::query()->where('email', 'ghost@example.com')->count());
    }

    public function test_purge_expired_command_deletes_old_consumed_rows(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload(['email' => 'purge@example.com']);
        $this->post(route('register'), $payload);

        PendingRegistration::query()->where('email', 'purge@example.com')->update([
            'expires_at' => now()->subDays(2),
            'consumed_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('auth:purge-expired-pending-registrations')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pending_registrations', ['email' => 'purge@example.com']);
    }
}
