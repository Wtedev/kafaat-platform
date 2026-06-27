<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class EmailOtpBaselineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_send_code_stores_hash_not_plaintext(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'otp@example.com']);

        app(EmailVerificationCodeService::class)->sendCode($user);

        $record = EmailVerificationCode::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertNotSame('', $record->code_hash);
        $this->assertFalse(Hash::needsRehash($record->code_hash));

        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use ($record): bool {
            return Hash::check($notification->code, $record->code_hash)
                && strlen($notification->code) === 6;
        });
    }

    public function test_verify_accepts_correct_code_and_consumes_record(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);
        $service = app(EmailVerificationCodeService::class);
        $service->sendCode($user);

        $code = null;
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $this->assertSame('success', $service->verify($user, (string) $code));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_verify_rejects_wrong_code_and_increments_attempts(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'wrong@example.com']);
        app(EmailVerificationCodeService::class)->sendCode($user);

        $result = app(EmailVerificationCodeService::class)->verify($user, '000000');

        $this->assertSame('invalid', $result);
        $this->assertSame(1, EmailVerificationCode::query()->where('user_id', $user->id)->value('attempts'));
    }

    public function test_verify_rejects_expired_code(): void
    {
        $user = User::factory()->create(['email' => 'expired@example.com']);

        EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $result = app(EmailVerificationCodeService::class)->verify($user, '123456');

        $this->assertSame('expired', $result);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_http_otp_verification_sets_session_flag(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
        ]);
        $user->assignRole('trainee');

        app(EmailVerificationCodeService::class)->sendCode($user);

        $code = null;
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $response->assertSessionHas('otp_verified', true);
    }
}
