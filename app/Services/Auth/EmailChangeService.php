<?php

namespace App\Services\Auth;

use App\Enums\AccountStatus;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Enums\UserActivityAction;
use App\Filament\Support\UserInlineEditSupport;
use App\Models\EmailVerificationCode;
use App\Models\PendingEmailChange;
use App\Models\User;
use App\Notifications\EmailChangedSecurityNotice;
use App\Notifications\EmailChangeVerificationCode;
use App\Services\Security\SecurityLogService;
use App\Services\UserActivityLogger;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class EmailChangeService
{
    public const EXPIRES_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MSG_INVALID_EMAIL = 'يرجى إدخال بريد إلكتروني صحيح.';

    public const MSG_SAME_AS_CURRENT = 'البريد الإلكتروني الجديد مطابق لبريدك الحالي.';

    public const MSG_IN_USE = 'لا يمكن استخدام هذا البريد الإلكتروني.';

    public const MSG_BAD_OTP = 'رمز التحقق غير صحيح.';

    public const MSG_EXPIRED_OTP = 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.';

    public const MSG_TOO_MANY_ATTEMPTS = 'تم تجاوز عدد المحاولات المسموح بها، يرجى المحاولة لاحقًا.';

    public const MSG_SUCCESS = 'تم تغيير البريد الإلكتروني بنجاح.';

    public const MSG_CONFIRM_MISMATCH = 'تأكيد البريد الإلكتروني غير متطابق.';

    public const MSG_ACCOUNT_BLOCKED = 'لا يمكن تغيير البريد الإلكتروني لهذا الحساب حالياً.';

    public const MSG_RESEND_COOLDOWN = 'يرجى الانتظار قبل إعادة إرسال رمز التحقق.';

    public const MSG_NO_PENDING = 'لا يوجد طلب تغيير بريد إلكتروني نشط.';

    public const MSG_RATE_LIMITED = 'تم تجاوز عدد المحاولات المسموح بها، يرجى المحاولة لاحقًا.';

    /**
     * @return array{ok: true, pending: PendingEmailChange, masked_email: string}|array{ok: false, message: string, field?: string}
     */
    public function start(User $user, string $email, ?string $emailConfirmation = null): array
    {
        if (! $this->isEligible($user)) {
            return $this->fail(self::MSG_ACCOUNT_BLOCKED);
        }

        if ($this->tooManyAttempts("email-change-send:{$user->id}", 5, 600)) {
            $this->securityLog('auth.email_change_rate_limited', SecurityLogResult::Blocked, SecurityLogSeverity::Warning, $user);

            return $this->fail(self::MSG_RATE_LIMITED);
        }

        $normalized = UserInlineEditSupport::normalizeAccountEmail($email);

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(self::MSG_INVALID_EMAIL, 'email');
        }

        if ($emailConfirmation !== null) {
            $confirmNormalized = UserInlineEditSupport::normalizeAccountEmail($emailConfirmation);
            if ($confirmNormalized !== $normalized) {
                return $this->fail(self::MSG_CONFIRM_MISMATCH, 'email_confirmation');
            }
        }

        $currentNormalized = UserInlineEditSupport::normalizeAccountEmail((string) $user->email);
        if ($normalized === $currentNormalized) {
            return $this->fail(self::MSG_SAME_AS_CURRENT, 'email');
        }

        if ($this->emailTakenByAnother($normalized, $user)) {
            // Generic message — do not reveal whether the address exists.
            $this->securityLog(
                'auth.email_change_rejected',
                SecurityLogResult::Denied,
                SecurityLogSeverity::Info,
                $user,
                identifier: $normalized,
                metadata: ['reason' => 'email_unavailable'],
            );

            return $this->fail(self::MSG_IN_USE, 'email');
        }

        RateLimiter::hit("email-change-send:{$user->id}", 600);

        $code = (string) random_int(100000, 999999);
        $attemptToken = (string) Str::uuid();

        $pending = PendingEmailChange::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'attempt_token' => $attemptToken,
                'pending_email' => $normalized,
                'current_email_snapshot' => $currentNormalized,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
                'last_sent_at' => now(),
            ],
        );

        $this->sendCodeToPendingEmail($normalized, $code);

        $this->securityLog(
            'auth.email_change_started',
            SecurityLogResult::Success,
            SecurityLogSeverity::Info,
            $user,
            identifier: $normalized,
            metadata: ['attempt_token_prefix' => substr($attemptToken, 0, 8)],
        );

        return [
            'ok' => true,
            'pending' => $pending->fresh(),
            'masked_email' => (string) SensitiveContactMasker::maskEmail($normalized),
        ];
    }

    /**
     * @return array{ok: true, pending: PendingEmailChange, masked_email: string, cooldown_seconds: int}|array{ok: false, message: string, cooldown_seconds?: int}
     */
    public function resend(User $user): array
    {
        if (! $this->isEligible($user)) {
            return $this->fail(self::MSG_ACCOUNT_BLOCKED);
        }

        if ($this->tooManyAttempts("email-change-resend:{$user->id}", 5, 600)) {
            $this->securityLog('auth.email_change_rate_limited', SecurityLogResult::Blocked, SecurityLogSeverity::Warning, $user);

            return $this->fail(self::MSG_RATE_LIMITED);
        }

        $pending = $this->activePending($user);
        if ($pending === null) {
            return $this->fail(self::MSG_NO_PENDING);
        }

        $cooldown = $this->resendCooldownRemaining($pending);
        if ($cooldown > 0) {
            return [
                'ok' => false,
                'message' => self::MSG_RESEND_COOLDOWN,
                'cooldown_seconds' => $cooldown,
            ];
        }

        if ($this->emailTakenByAnother($pending->pending_email, $user)) {
            $pending->delete();

            return $this->fail(self::MSG_IN_USE);
        }

        RateLimiter::hit("email-change-resend:{$user->id}", 600);

        $code = (string) random_int(100000, 999999);

        $pending->forceFill([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'last_sent_at' => now(),
        ])->save();

        $this->sendCodeToPendingEmail($pending->pending_email, $code);

        $this->securityLog(
            'auth.email_change_resent',
            SecurityLogResult::Success,
            SecurityLogSeverity::Info,
            $user,
            identifier: $pending->pending_email,
            metadata: ['attempt_token_prefix' => substr($pending->attempt_token, 0, 8)],
        );

        return [
            'ok' => true,
            'pending' => $pending->fresh(),
            'masked_email' => (string) SensitiveContactMasker::maskEmail($pending->pending_email),
            'cooldown_seconds' => self::RESEND_COOLDOWN_SECONDS,
        ];
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, message: string, field?: string}
     */
    public function verify(User $user, string $code): array
    {
        if (! $this->isEligible($user)) {
            return $this->fail(self::MSG_ACCOUNT_BLOCKED);
        }

        if ($this->tooManyAttempts("email-change-verify:{$user->id}", 20, 600)) {
            $this->securityLog('auth.email_change_rate_limited', SecurityLogResult::Blocked, SecurityLogSeverity::Warning, $user);

            return $this->fail(self::MSG_RATE_LIMITED, 'code');
        }

        RateLimiter::hit("email-change-verify:{$user->id}", 600);

        $pending = $this->activePending($user);
        if ($pending === null) {
            return $this->fail(self::MSG_EXPIRED_OTP, 'code');
        }

        if ($pending->isExpired()) {
            $pending->delete();
            $this->securityLog('auth.email_change_expired', SecurityLogResult::Failed, SecurityLogSeverity::Info, $user);

            return $this->fail(self::MSG_EXPIRED_OTP, 'code');
        }

        if ($pending->attempts >= self::MAX_ATTEMPTS) {
            $pending->delete();
            $this->securityLog('auth.email_change_locked', SecurityLogResult::Blocked, SecurityLogSeverity::Warning, $user);

            return $this->fail(self::MSG_TOO_MANY_ATTEMPTS, 'code');
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->increment('attempts');
            $pending->refresh();

            if ($pending->attempts >= self::MAX_ATTEMPTS) {
                $pending->delete();
                $this->securityLog('auth.email_change_locked', SecurityLogResult::Blocked, SecurityLogSeverity::Warning, $user);

                return $this->fail(self::MSG_TOO_MANY_ATTEMPTS, 'code');
            }

            $this->securityLog('auth.email_change_otp_failed', SecurityLogResult::Failed, SecurityLogSeverity::Info, $user, metadata: [
                'reason' => 'invalid',
            ]);

            return $this->fail(self::MSG_BAD_OTP, 'code');
        }

        $newEmail = $pending->pending_email;
        $oldEmail = (string) $user->email;
        $attemptToken = $pending->attempt_token;

        if ($this->emailTakenByAnother($newEmail, $user)) {
            $pending->delete();

            return $this->fail(self::MSG_IN_USE, 'code');
        }

        try {
            DB::transaction(function () use ($user, $pending, $newEmail): void {
                // Lock the user row to serialize concurrent commits.
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (! $this->isEligible($locked)) {
                    throw new EmailChangeAbortedException(self::MSG_ACCOUNT_BLOCKED);
                }

                $currentNormalized = UserInlineEditSupport::normalizeAccountEmail((string) $locked->email);
                if ($currentNormalized !== $pending->current_email_snapshot) {
                    throw new EmailChangeAbortedException(self::MSG_EXPIRED_OTP);
                }

                if ($this->emailTakenByAnother($newEmail, $locked)) {
                    throw new EmailChangeAbortedException(self::MSG_IN_USE);
                }

                $locked->forceFill([
                    'email' => $newEmail,
                    // OTP ownership proof of the new address — mark verified.
                    'email_verified_at' => now(),
                ])->save();

                $pending->delete();

                // Invalidate any leftover login/verify OTP tied to the previous address.
                EmailVerificationCode::query()->where('user_id', $locked->id)->delete();
            });
        } catch (EmailChangeAbortedException $exception) {
            $pending->delete();

            return $this->fail($exception->getMessage(), 'code');
        } catch (UniqueConstraintViolationException $exception) {
            $pending->delete();
            Log::warning('auth.email_change_unique_race', [
                'exception' => $exception::class,
                'user_id' => $user->id,
            ]);

            return $this->fail(self::MSG_IN_USE, 'code');
        } catch (Throwable $exception) {
            Log::warning('auth.email_change_commit_failed', [
                'exception' => $exception::class,
                'user_id' => $user->id,
            ]);

            throw $exception;
        }

        $user->refresh();

        UserActivityLogger::log($user, UserActivityAction::EmailChanged, 'غيّر المستفيد بريده الإلكتروني بعد التحقق برمز OTP.');

        $this->securityLog(
            'auth.email_change_completed',
            SecurityLogResult::Success,
            SecurityLogSeverity::Info,
            $user,
            identifier: $newEmail,
            metadata: ['attempt_token_prefix' => substr($attemptToken, 0, 8)],
        );

        $this->notifyOldEmail($oldEmail);

        return [
            'ok' => true,
            'message' => self::MSG_SUCCESS,
        ];
    }

    public function cancel(User $user): void
    {
        PendingEmailChange::query()->where('user_id', $user->id)->delete();

        $this->securityLog(
            'auth.email_change_cancelled',
            SecurityLogResult::Success,
            SecurityLogSeverity::Info,
            $user,
        );
    }

    public function pendingFor(User $user): ?PendingEmailChange
    {
        return $this->activePending($user);
    }

    public function resendCooldownRemaining(?PendingEmailChange $pending): int
    {
        if ($pending === null || $pending->last_sent_at === null) {
            return 0;
        }

        $elapsed = $pending->last_sent_at->diffInSeconds(now());
        $remaining = self::RESEND_COOLDOWN_SECONDS - (int) $elapsed;

        return max(0, $remaining);
    }

    public function isEligible(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAnonymized()) {
            return false;
        }

        if (! $user->allowsOperationalAccess()) {
            return false;
        }

        if (in_array($user->account_status, [
            AccountStatus::Inactive,
            AccountStatus::Anonymized,
            AccountStatus::DeletionPending,
            AccountStatus::DeletionProcessing,
        ], true)) {
            return false;
        }

        return true;
    }

    private function activePending(User $user): ?PendingEmailChange
    {
        $pending = PendingEmailChange::query()->where('user_id', $user->id)->first();

        if ($pending === null) {
            return null;
        }

        $currentNormalized = UserInlineEditSupport::normalizeAccountEmail((string) $user->email);
        if ($pending->current_email_snapshot !== $currentNormalized) {
            $pending->delete();

            return null;
        }

        if (! $this->isEligible($user)) {
            $pending->delete();

            return null;
        }

        if ($pending->isExpired()) {
            $pending->delete();

            return null;
        }

        return $pending;
    }

    private function emailTakenByAnother(string $normalizedEmail, User $user): bool
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    private function sendCodeToPendingEmail(string $pendingEmail, string $code): void
    {
        // Deliver to the *new* address without mutating users.email.
        Notification::route('mail', $pendingEmail)
            ->notify(new EmailChangeVerificationCode($code, self::EXPIRES_MINUTES));
    }

    private function notifyOldEmail(string $oldEmail): void
    {
        $normalized = UserInlineEditSupport::normalizeAccountEmail($oldEmail);
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Notification::route('mail', $normalized)
                ->notify(new EmailChangedSecurityNotice);
        } catch (Throwable $exception) {
            Log::warning('auth.email_change_old_notify_failed', [
                'exception' => $exception::class,
            ]);
        }
    }

    private function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * @return array{ok: false, message: string, field?: string}
     */
    private function fail(string $message, ?string $field = null): array
    {
        $result = ['ok' => false, 'message' => $message];

        if ($field !== null) {
            $result['field'] = $field;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function securityLog(
        string $event,
        SecurityLogResult $result,
        SecurityLogSeverity $severity,
        User $user,
        ?string $identifier = null,
        ?array $metadata = null,
    ): void {
        app(SecurityLogService::class)->record(
            $event,
            $result,
            $severity,
            $user,
            identifier: $identifier,
            metadata: $metadata,
            request: request(),
        );
    }
}
