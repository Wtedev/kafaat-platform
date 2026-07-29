<?php

namespace Tests\Concerns;

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\SignupEmailVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

trait CompletesSignupViaOtp
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postRegisterAwaitingOtp(array $payload): TestResponse
    {
        return $this->post(route('register'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function registerAndVerifyOtp(array $payload): User
    {
        Notification::fake();

        $this->postRegisterAwaitingOtp($payload)
            ->assertRedirect(route('register.verify.show'));

        $this->assertSame(0, User::query()->where('email', $payload['email'])->count());

        $pending = PendingRegistration::query()->where('email', strtolower($payload['email']))->first();
        $this->assertNotNull($pending);

        $code = null;
        Notification::assertSentOnDemand(SignupEmailVerificationCode::class, function (SignupEmailVerificationCode $notification) use (&$code, $pending): bool {
            $code = $notification->code;

            return Hash::check($notification->code, $pending->fresh()->code_hash);
        });

        $this->assertNotNull($code);

        $this->post(route('register.verify'), ['code' => $code])
            ->assertRedirect();

        $user = User::query()->where('email', strtolower($payload['email']))->first();
        $this->assertNotNull($user);

        return $user;
    }

    protected function latestSignupOtpCode(string $email): string
    {
        $pending = PendingRegistration::query()->where('email', strtolower($email))->firstOrFail();
        $code = null;

        Notification::assertSentOnDemand(SignupEmailVerificationCode::class, function (SignupEmailVerificationCode $notification) use (&$code, $pending): bool {
            if (Hash::check($notification->code, $pending->fresh()->code_hash)) {
                $code = $notification->code;

                return true;
            }

            return false;
        });

        $this->assertNotNull($code);

        return (string) $code;
    }
}
