<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\UserActivityLogger;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    /** مدة صلاحية الرمز بالدقائق. */
    public const EXPIRES_MINUTES = 15;

    /** أقصى عدد محاولات إدخال خاطئة قبل إبطال الرمز. */
    public const MAX_ATTEMPTS = 5;

    /**
     * يولّد رمزاً جديداً (مع تخزينه مجزّأً) ويرسله للمستخدم.
     */
    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            ],
        );

        $user->notify(new VerifyEmailCode($code, self::EXPIRES_MINUTES));
    }

    /**
     * يتحقق من الرمز المُدخل.
     *
     * @return string 'success' | 'expired' | 'too_many_attempts' | 'invalid' | 'not_found'
     */
    public function verify(User $user, string $code): string
    {
        $record = EmailVerificationCode::where('user_id', $user->id)->first();

        if ($record === null) {
            return 'not_found';
        }

        if ($record->isExpired()) {
            $record->delete();

            return 'expired';
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->delete();

            return 'too_many_attempts';
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return 'invalid';
        }

        $record->delete();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            UserActivityLogger::logEmailVerified($user);
        }

        return 'success';
    }
}
