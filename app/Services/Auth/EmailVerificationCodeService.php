<?php

namespace App\Services\Auth;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Security\SecurityLogService;
use App\Services\UserActivityLogger;
use Illuminate\Support\Facades\Hash;
use Throwable;

class EmailVerificationCodeService
{
    /** مدة صلاحية الرمز بالدقائق. */
    public const EXPIRES_MINUTES = 15;

    /** أقصى عدد محاولات إدخال خاطئة قبل إبطال الرمز. */
    public const MAX_ATTEMPTS = 5;

    /** علامة الجلسة عندما يفشل تسليم الرمز، حتى تعرضها صفحة التحقق. */
    public const SEND_FAILED_SESSION_KEY = 'otp_send_failed';

    public const SEND_FAILED_MESSAGE = 'تعذّر إرسال رمز التحقق. اضغط إعادة الإرسال أو حاول بعد قليل';

    /**
     * يولّد رمزاً ويرسله، ولا يحفظه إلا بعد نجاح الإرسال.
     */
    public function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        try {
            $user->notify(new VerifyEmailCode($code, self::EXPIRES_MINUTES));
        } catch (Throwable $exception) {
            session()->put(self::SEND_FAILED_SESSION_KEY, true);
            $this->recordSendFailure($user, $exception);

            throw $exception;
        }

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            ],
        );

        session()->forget(self::SEND_FAILED_SESSION_KEY);
    }

    private function recordSendFailure(User $user, Throwable $exception): void
    {
        app(SecurityLogService::class)->record(
            'auth.otp_send_failed',
            SecurityLogResult::Failed,
            SecurityLogSeverity::Warning,
            $user,
            metadata: ['message' => $exception->getMessage()],
            request: request(),
        );
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
