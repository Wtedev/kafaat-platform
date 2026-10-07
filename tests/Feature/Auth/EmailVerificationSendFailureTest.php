<?php

namespace Tests\Feature\Auth;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\EmailVerificationCode;
use App\Models\SecurityLog;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class EmailVerificationSendFailureTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->withoutVite();
        $this->useFailingMailer();
    }

    public function test_failed_send_does_not_leave_a_valid_code_and_is_written_to_the_security_log(): void
    {
        $user = User::factory()->create(['email' => 'otp-fail@example.com']);

        try {
            app(EmailVerificationCodeService::class)->sendCode($user);
            $this->fail('Expected the mail transport to fail.');
        } catch (TransportException $exception) {
            $this->assertSame('smtp down', $exception->getMessage());
        }

        $this->assertSame(0, EmailVerificationCode::query()->where('user_id', $user->id)->count());
        $this->assertTrue(session()->has(EmailVerificationCodeService::SEND_FAILED_SESSION_KEY));

        $log = SecurityLog::query()->where('event', 'auth.otp_send_failed')->where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(SecurityLogResult::Failed, $log->result);
        $this->assertSame(SecurityLogSeverity::Warning, $log->severity);
        $this->assertSame('smtp down', $log->metadata['message'] ?? null);
        $this->assertStringNotContainsString($user->email, json_encode($log->metadata, JSON_UNESCAPED_UNICODE));
    }

    public function test_login_and_resend_show_the_failure_message_until_mail_works_again(): void
    {
        $user = User::factory()->create([
            'email' => 'otp-login@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'beneficiary',
            'is_active' => true,
        ]);
        $user->assignRole('beneficiary');

        $this->post(route('login'), [
            'email' => 'otp-login@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee(EmailVerificationCodeService::SEND_FAILED_MESSAGE, false)
            ->assertDontSee('أرسلنا رمز تحقق مكوّناً من 6 أرقام', false);

        $this->assertSame(0, EmailVerificationCode::query()->where('user_id', $user->id)->count());
        $this->assertGreaterThanOrEqual(
            1,
            SecurityLog::query()->where('event', 'auth.otp_send_failed')->where('user_id', $user->id)->count(),
        );

        $this->post(route('verification.send'))
            ->assertRedirect();

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee(EmailVerificationCodeService::SEND_FAILED_MESSAGE, false)
            ->assertDontSee('تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.', false);

        $this->assertSame(0, EmailVerificationCode::query()->where('user_id', $user->id)->count());

        config(['mail.default' => 'array']);

        $this->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.');

        $this->assertSame(1, EmailVerificationCode::query()->where('user_id', $user->id)->count());
        $this->assertFalse(session()->has(EmailVerificationCodeService::SEND_FAILED_SESSION_KEY));

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.', false)
            ->assertDontSee(EmailVerificationCodeService::SEND_FAILED_MESSAGE, false);
    }

    public function test_retry_sends_the_code_after_the_mailer_recovers(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'otp-retry@example.com']);

        app(EmailVerificationCodeService::class)->sendCode($user);

        $this->assertSame(1, EmailVerificationCode::query()->where('user_id', $user->id)->count());
        $this->assertFalse(session()->has(EmailVerificationCodeService::SEND_FAILED_SESSION_KEY));
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    private function useFailingMailer(): void
    {
        Mail::extend('failing', function () {
            return new class implements TransportInterface
            {
                public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                {
                    throw new TransportException('smtp down');
                }

                public function __toString(): string
                {
                    return 'failing';
                }
            };
        });

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => ['transport' => 'failing'],
        ]);
    }
}
