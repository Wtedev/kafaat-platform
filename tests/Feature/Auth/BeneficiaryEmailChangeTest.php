<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserActivityAction;
use App\Models\EmailVerificationCode;
use App\Models\PendingEmailChange;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Notifications\EmailChangedSecurityNotice;
use App\Notifications\EmailChangeVerificationCode;
use App\Notifications\SignupEmailVerificationCode;
use App\Notifications\VerifyEmailCode;
use App\Services\Auth\EmailChangeService;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\CompletesSignupViaOtp;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class BeneficiaryEmailChangeTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use CompletesSignupViaOtp;
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    private EmailChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
        $this->service = app(EmailChangeService::class);
        RateLimiter::clear('email-change-send:1');
    }

    public function test_guest_cannot_start_email_change(): void
    {
        $this->post(route('portal.settings.email.change'), [
            'email' => 'new@example.com',
            'email_confirmation' => 'new@example.com',
        ])->assertRedirect(route('login'));
    }

    public function test_account_page_shows_email_section_and_change_button(): void
    {
        $user = $this->makeBeneficiary('current@example.com');

        $this->actingAsOtpVerified($user)
            ->get(route('portal.settings.account'))
            ->assertOk()
            ->assertSee('البريد الإلكتروني', false)
            ->assertSee('current@example.com', false)
            ->assertSee('تم التحقق', false)
            ->assertSee('تغيير البريد الإلكتروني', false);
    }

    public function test_account_page_shows_unverified_badge(): void
    {
        $user = $this->makeBeneficiary('unverified@example.com', [
            'email_verified_at' => null,
        ]);

        $this->actingAsOtpVerified($user)
            ->get(route('portal.settings.account'))
            ->assertOk()
            ->assertSee('غير مُتحقق', false);
    }

    public function test_start_sends_otp_to_new_email_without_changing_users_email(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('old@example.com');

        $result = $this->service->start($user, 'new@example.com', 'new@example.com');

        $this->assertTrue($result['ok']);
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('pending_email_changes', [
            'user_id' => $user->id,
            'pending_email' => 'new@example.com',
            'current_email_snapshot' => 'old@example.com',
        ]);

        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'new@example.com'
                && strlen($notification->code) === 6;
        });
    }

    public function test_start_stores_hashed_otp_not_plaintext(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('hash@example.com');
        $this->service->start($user, 'hash-new@example.com', 'hash-new@example.com');

        $pending = PendingEmailChange::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($pending);
        $this->assertFalse(Hash::needsRehash($pending->code_hash));

        $plain = null;
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification) use (&$plain) {
            $plain = $notification->code;

            return true;
        });

        $this->assertTrue(Hash::check((string) $plain, $pending->code_hash));
        $this->assertStringNotContainsString((string) $plain, $pending->code_hash);
    }

    public function test_start_normalizes_trim_and_lowercase(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('norm@example.com');
        $result = $this->service->start($user, '  New.Email@Example.COM  ', '  New.Email@Example.COM  ');

        $this->assertTrue($result['ok']);
        $this->assertSame('new.email@example.com', PendingEmailChange::query()->where('user_id', $user->id)->value('pending_email'));
    }

    public function test_start_rejects_invalid_email(): void
    {
        $user = $this->makeBeneficiary('valid@example.com');
        $result = $this->service->start($user, 'not-an-email', 'not-an-email');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_INVALID_EMAIL, $result['message']);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
    }

    public function test_start_rejects_same_as_current(): void
    {
        $user = $this->makeBeneficiary('same@example.com');
        $result = $this->service->start($user, 'SAME@example.com', 'SAME@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_SAME_AS_CURRENT, $result['message']);
    }

    public function test_start_rejects_confirmation_mismatch(): void
    {
        $user = $this->makeBeneficiary('confirm@example.com');
        $result = $this->service->start($user, 'a@example.com', 'b@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_CONFIRM_MISMATCH, $result['message']);
    }

    public function test_start_rejects_email_in_use_with_generic_message(): void
    {
        Notification::fake();

        $this->makeBeneficiary('taken@example.com');
        $user = $this->makeBeneficiary('me@example.com');

        $result = $this->service->start($user, 'taken@example.com', 'taken@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_IN_USE, $result['message']);
        Notification::assertNothingSent();
    }

    public function test_start_rejects_case_insensitive_duplicate(): void
    {
        $this->makeBeneficiary('Taken.User@example.com');
        $user = $this->makeBeneficiary('me2@example.com');

        $result = $this->service->start($user, 'taken.user@example.com', 'taken.user@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_IN_USE, $result['message']);
    }

    public function test_http_start_success_redirects_to_otp_step(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('http-start@example.com');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.settings.email.change'), [
                'email' => 'http-new@example.com',
                'email_confirmation' => 'http-new@example.com',
            ])
            ->assertRedirect(route('portal.settings.account'))
            ->assertSessionHas('email_change_step', 'otp');

        $this->assertSame('http-start@example.com', $user->fresh()->email);
    }

    public function test_http_start_invalid_email_shows_arabic_message(): void
    {
        $user = $this->makeBeneficiary('http-invalid@example.com');

        $this->actingAsOtpVerified($user)
            ->from(route('portal.settings.account'))
            ->post(route('portal.settings.email.change'), [
                'email' => 'bad',
                'email_confirmation' => 'bad',
            ])
            ->assertRedirect(route('portal.settings.account'))
            ->assertSessionHasErrors(['email' => EmailChangeService::MSG_INVALID_EMAIL]);
    }

    public function test_verify_success_changes_email_marks_verified_and_notifies_old(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('before@example.com', [
            'email_verified_at' => null,
            'notify_email' => true,
            'phone' => '0500000001',
            'role_type' => 'beneficiary',
        ]);
        $passwordHash = $user->password;

        $code = $this->startAndCaptureCode($user, 'after@example.com');

        $result = $this->service->verify($user, $code);

        $this->assertTrue($result['ok']);
        $this->assertSame(EmailChangeService::MSG_SUCCESS, $result['message']);

        $fresh = $user->fresh();
        $this->assertSame('after@example.com', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertTrue($fresh->notify_email);
        $this->assertSame('0500000001', $fresh->phone);
        $this->assertSame('beneficiary', $fresh->role_type);
        $this->assertSame($passwordHash, $fresh->password);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);

        Notification::assertSentOnDemand(EmailChangedSecurityNotice::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'before@example.com';
        });

        $this->assertTrue(
            UserActivityLog::query()
                ->where('user_id', $user->id)
                ->where('action', UserActivityAction::EmailChanged)
                ->exists()
        );
    }

    public function test_verify_rejects_wrong_otp(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('wrong-otp@example.com');
        $this->service->start($user, 'wrong-new@example.com', 'wrong-new@example.com');

        $result = $this->service->verify($user, '000000');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_BAD_OTP, $result['message']);
        $this->assertSame('wrong-otp@example.com', $user->fresh()->email);
        $this->assertSame(1, PendingEmailChange::query()->where('user_id', $user->id)->value('attempts'));
    }

    public function test_verify_rejects_expired_otp(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('expired-otp@example.com');
        $this->service->start($user, 'expired-new@example.com', 'expired-new@example.com');

        PendingEmailChange::query()->where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $result = $this->service->verify($user, '123456');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_EXPIRED_OTP, $result['message']);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
        $this->assertSame('expired-otp@example.com', $user->fresh()->email);
    }

    public function test_verify_locks_after_max_attempts(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('max-attempts@example.com');
        $this->service->start($user, 'max-new@example.com', 'max-new@example.com');

        for ($i = 0; $i < EmailChangeService::MAX_ATTEMPTS - 1; $i++) {
            $this->assertSame(EmailChangeService::MSG_BAD_OTP, $this->service->verify($user, '000000')['message']);
        }

        $result = $this->service->verify($user, '000000');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_TOO_MANY_ATTEMPTS, $result['message']);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
    }

    public function test_otp_cannot_be_reused_after_success(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('reuse@example.com');
        $code = $this->startAndCaptureCode($user, 'reuse-new@example.com');

        $this->assertTrue($this->service->verify($user, $code)['ok']);
        $this->assertFalse($this->service->verify($user->fresh(), $code)['ok']);
    }

    public function test_cancel_keeps_current_email_and_clears_pending(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('cancel@example.com');
        $this->service->start($user, 'cancel-new@example.com', 'cancel-new@example.com');

        $this->actingAsOtpVerified($user)
            ->post(route('portal.settings.email.change.cancel'))
            ->assertRedirect(route('portal.settings.account'));

        $this->assertSame('cancel@example.com', $user->fresh()->email);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
    }

    public function test_resend_respects_cooldown(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('cooldown@example.com');
        $this->service->start($user, 'cooldown-new@example.com', 'cooldown-new@example.com');

        $result = $this->service->resend($user);

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_RESEND_COOLDOWN, $result['message']);
        $this->assertGreaterThan(0, $result['cooldown_seconds']);
    }

    public function test_resend_after_cooldown_issues_new_code(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('resend@example.com');
        $firstCode = $this->startAndCaptureCode($user, 'resend-new@example.com');

        PendingEmailChange::query()->where('user_id', $user->id)->update([
            'last_sent_at' => now()->subSeconds(EmailChangeService::RESEND_COOLDOWN_SECONDS + 1),
        ]);

        Notification::fake();
        $result = $this->service->resend($user);
        $this->assertTrue($result['ok']);

        $secondCode = null;
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification) use (&$secondCode) {
            $secondCode = $notification->code;

            return true;
        });

        $this->assertNotSame($firstCode, $secondCode);
        $this->assertFalse($this->service->verify($user, $firstCode)['ok']);
        $this->assertTrue($this->service->verify($user->fresh(), (string) $secondCode)['ok']);
    }

    public function test_pending_invalidated_when_current_email_changes(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('snap@example.com');
        $code = $this->startAndCaptureCode($user, 'snap-new@example.com');

        $user->forceFill(['email' => 'admin-changed@example.com'])->save();

        $result = $this->service->verify($user->fresh(), $code);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
    }

    public function test_blocks_inactive_account(): void
    {
        $user = $this->makeBeneficiary('inactive@example.com', ['is_active' => false]);

        $result = $this->service->start($user, 'inactive-new@example.com', 'inactive-new@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_ACCOUNT_BLOCKED, $result['message']);
    }

    public function test_blocks_anonymized_account(): void
    {
        $user = $this->makeBeneficiary('anon@example.com', [
            'account_status' => AccountStatus::Anonymized,
            'anonymized_at' => now(),
        ]);

        $result = $this->service->start($user, 'anon-new@example.com', 'anon-new@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_ACCOUNT_BLOCKED, $result['message']);
    }

    public function test_blocks_deletion_pending_account(): void
    {
        $user = $this->makeBeneficiary('del-pending@example.com', [
            'account_status' => AccountStatus::DeletionPending,
        ]);

        $result = $this->service->start($user, 'del-pending-new@example.com', 'del-pending-new@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_ACCOUNT_BLOCKED, $result['message']);
    }

    public function test_blocks_deletion_processing_account(): void
    {
        $user = $this->makeBeneficiary('del-proc@example.com', [
            'account_status' => AccountStatus::DeletionProcessing,
            'is_active' => false,
        ]);

        $result = $this->service->start($user, 'del-proc-new@example.com', 'del-proc-new@example.com');

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_ACCOUNT_BLOCKED, $result['message']);
    }

    public function test_cannot_change_another_user_via_auth_context(): void
    {
        Notification::fake();

        $attacker = $this->makeBeneficiary('attacker@example.com');
        $victim = $this->makeBeneficiary('victim@example.com');

        $this->actingAsOtpVerified($attacker)
            ->post(route('portal.settings.email.change'), [
                'email' => 'attacker-new@example.com',
                'email_confirmation' => 'attacker-new@example.com',
                'user_id' => $victim->id,
            ])
            ->assertRedirect(route('portal.settings.account'));

        $this->assertSame('victim@example.com', $victim->fresh()->email);
        $this->assertDatabaseHas('pending_email_changes', [
            'user_id' => $attacker->id,
            'pending_email' => 'attacker-new@example.com',
        ]);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $victim->id]);
    }

    public function test_http_verify_success_message_and_session_preserved(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('http-ok@example.com');
        $code = $this->startAndCaptureCode($user, 'http-ok-new@example.com');

        $response = $this->actingAsOtpVerified($user)
            ->post(route('portal.settings.email.change.verify'), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('portal.settings.account'))
            ->assertSessionHas('success', EmailChangeService::MSG_SUCCESS)
            ->assertSessionHas('otp_verified', true);

        $this->assertSame('http-ok-new@example.com', $user->fresh()->email);
    }

    public function test_login_works_with_new_email_not_old_after_change(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('login-old@example.com');
        $code = $this->startAndCaptureCode($user, 'login-new@example.com');
        $this->assertTrue($this->service->verify($user, $code)['ok']);

        $this->assertTrue(auth()->attempt([
            'email' => 'login-new@example.com',
            'password' => 'password',
        ]));
        auth()->logout();

        $this->assertFalse(auth()->attempt([
            'email' => 'login-old@example.com',
            'password' => 'password',
        ]));
    }

    public function test_activity_log_does_not_contain_full_email_or_otp(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('privacy-log@example.com');
        $code = $this->startAndCaptureCode($user, 'privacy-log-new@example.com');
        $this->service->verify($user, $code);

        $log = UserActivityLog::query()
            ->where('user_id', $user->id)
            ->where('action', UserActivityAction::EmailChanged)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('privacy-log@example.com', (string) $log->detail);
        $this->assertStringNotContainsString('privacy-log-new@example.com', (string) $log->detail);
        $this->assertStringNotContainsString($code, (string) $log->detail);
    }

    public function test_existing_login_otp_flow_unaffected(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('login-otp@example.com');
        app(EmailVerificationCodeService::class)->sendCode($user);

        Notification::assertSentTo($user, VerifyEmailCode::class);

        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_otp_codes_do_not_cross_work_across_signup_login_and_email_change(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('cross-purpose@example.com');

        EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $emailChangeCode = $this->startAndCaptureCode($user, 'cross-purpose-new@example.com');

        $signupPayload = $this->validRegistrationPayload(['email' => 'cross-signup@example.com']);
        $this->postRegisterAwaitingOtp($signupPayload)
            ->assertRedirect(route('register.verify.show'));

        $signupCode = $this->latestSignupOtpCode('cross-signup@example.com');

        $this->assertDatabaseCount('pending_email_changes', 1);
        $this->assertDatabaseCount('pending_registrations', 1);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);

        // Signup OTP must not complete an email-change attempt.
        $this->assertFalse($this->service->verify($user->fresh(), $signupCode)['ok']);
        $this->assertSame('cross-purpose@example.com', $user->fresh()->email);
        $this->assertDatabaseHas('pending_email_changes', ['user_id' => $user->id]);

        // Email-change OTP must not complete signup verification (same HTTP session as register).
        $this->from(route('register.verify.show'))
            ->post(route('register.verify'), ['code' => $emailChangeCode])
            ->assertRedirect(route('register.verify.show'))
            ->assertSessionHasErrors('code');

        $this->assertSame(0, User::query()->where('email', 'cross-signup@example.com')->count());
        $this->assertNull(
            PendingRegistration::query()->where('email', 'cross-signup@example.com')->value('consumed_at')
        );

        // Login OTP table remains independent of email-change pending rows.
        $this->assertTrue(
            Hash::check('111111', EmailVerificationCode::query()->where('user_id', $user->id)->value('code_hash'))
        );
        $this->assertFalse(
            Hash::check($emailChangeCode, EmailVerificationCode::query()->where('user_id', $user->id)->value('code_hash'))
        );

        Notification::assertSentOnDemand(SignupEmailVerificationCode::class);
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class);
    }

    public function test_email_change_invalidates_old_login_otp_codes(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('invalidate-otp@example.com');
        EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $code = $this->startAndCaptureCode($user, 'invalidate-otp-new@example.com');
        $this->assertTrue($this->service->verify($user, $code)['ok']);

        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_masked_pending_email_shown_on_account_page(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('mask@example.com');
        $this->service->start($user, 'masked-new@example.com', 'masked-new@example.com');

        $this->actingAsOtpVerified($user)
            ->withSession(['email_change_step' => 'otp'])
            ->get(route('portal.settings.account'))
            ->assertOk()
            ->assertSee('m***@example.com', false)
            ->assertDontSee('masked-new@example.com', false);
    }

    public function test_restart_after_cancel_works(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('restart@example.com');
        $this->service->start($user, 'restart-a@example.com', 'restart-a@example.com');
        $this->service->cancel($user);

        $result = $this->service->start($user, 'restart-b@example.com', 'restart-b@example.com');

        $this->assertTrue($result['ok']);
        $this->assertSame('restart-b@example.com', PendingEmailChange::query()->where('user_id', $user->id)->value('pending_email'));
    }

    public function test_verify_race_when_email_taken_returns_generic_message(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('race@example.com');
        $code = $this->startAndCaptureCode($user, 'race-new@example.com');

        $this->makeBeneficiary('race-new@example.com');

        $result = $this->service->verify($user, $code);

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_IN_USE, $result['message']);
        $this->assertSame('race@example.com', $user->fresh()->email);
    }

    public function test_pending_attempt_bound_to_user_and_pending_email(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('bound@example.com');
        $this->service->start($user, 'bound-new@example.com', 'bound-new@example.com');

        $pending = PendingEmailChange::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($pending);
        $this->assertTrue(Str::isUuid($pending->attempt_token));
        $this->assertSame('bound-new@example.com', $pending->pending_email);
        $this->assertSame('bound@example.com', $pending->current_email_snapshot);
        $this->assertTrue($pending->expires_at->greaterThan(now()->addMinutes(EmailChangeService::EXPIRES_MINUTES - 1)));
    }

    public function test_user_without_otp_cannot_access_email_change_routes(): void
    {
        $user = $this->makeBeneficiary('no-otp-gate@example.com');

        $this->actingAs($user)
            ->post(route('portal.settings.email.change'), [
                'email' => 'x@example.com',
                'email_confirmation' => 'x@example.com',
            ])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_http_bad_otp_arabic_message(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('http-bad@example.com');
        $this->service->start($user, 'http-bad-new@example.com', 'http-bad-new@example.com');

        $this->actingAsOtpVerified($user)
            ->from(route('portal.settings.account'))
            ->post(route('portal.settings.email.change.verify'), [
                'code' => '000000',
            ])
            ->assertRedirect(route('portal.settings.account'))
            ->assertSessionHasErrors(['code' => EmailChangeService::MSG_BAD_OTP]);
    }

    public function test_start_replaces_previous_pending_attempt(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('replace@example.com');
        $firstCode = $this->startAndCaptureCode($user, 'replace-a@example.com');

        Notification::fake();
        $secondCode = $this->startAndCaptureCode($user, 'replace-b@example.com');

        $this->assertSame(1, PendingEmailChange::query()->where('user_id', $user->id)->count());
        $this->assertSame('replace-b@example.com', PendingEmailChange::query()->where('user_id', $user->id)->value('pending_email'));
        $this->assertNotSame($firstCode, $secondCode);

        $this->assertFalse($this->service->verify($user, $firstCode)['ok']);
        $this->assertSame('replace@example.com', $user->fresh()->email);

        // Fresh attempt after failed old code.
        Notification::fake();
        $thirdCode = $this->startAndCaptureCode($user->fresh(), 'replace-c@example.com');
        $this->assertTrue($this->service->verify($user->fresh(), $thirdCode)['ok']);
        $this->assertSame('replace-c@example.com', $user->fresh()->email);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBeneficiary(string $email, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => $email,
            'role_type' => 'beneficiary',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ], $overrides));

        $user->assignRole('beneficiary');

        RateLimiter::clear('email-change-send:'.$user->id);
        RateLimiter::clear('email-change-resend:'.$user->id);
        RateLimiter::clear('email-change-verify:'.$user->id);

        return $user;
    }

    private function startAndCaptureCode(User $user, string $newEmail): string
    {
        $this->service->start($user, $newEmail, $newEmail);

        $code = null;
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->assertNotNull($code);

        return (string) $code;
    }
}
