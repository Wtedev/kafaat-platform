<?php

namespace App\Services\Auth;

use App\Models\PendingRegistration;
use App\Models\PrivacyPolicyVersion;
use App\Models\User;
use App\Notifications\SignupEmailVerificationCode;
use App\Services\Privacy\PrivacyPolicyService;
use App\Services\UserActivityLogger;
use App\Support\Auth\EmailNormalizer;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

class PendingRegistrationService
{
    public const SESSION_KEY = 'pending_registration_id';

    public const EXPIRES_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_RESENDS = 5;

    public function __construct(
        private readonly UserRegistrationService $registrationService,
    ) {}

    public static function normalizeEmail(string $email): string
    {
        return EmailNormalizer::normalize($email);
    }

    public function emailExists(string $normalizedEmail): bool
    {
        return User::query()
            ->whereEmailIgnoreCase($normalizedEmail)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function start(array $validated, Request $request): PendingRegistration
    {
        $this->purgeExpired();

        $email = self::normalizeEmail((string) $validated['email']);

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('email_in_use');
        }

        $intendedUrl = SafeLoginReturnUrl::sanitize(
            $request->session()->get(SafeLoginReturnUrl::SESSION_KEY)
        );

        $this->invalidateSessionPending($request);
        $this->invalidateActiveForEmail($email);

        $code = $this->generateCode();

        $payload = [
            'first_name' => $validated['first_name'],
            'father_name' => $validated['father_name'],
            'grandfather_name' => $validated['grandfather_name'],
            'family_name' => $validated['family_name'],
            'identity_type' => $validated['identity_type'],
            'identity_number' => $validated['identity_number'],
            'birth_date' => $validated['birth_date'],
            'gender' => $validated['gender'] instanceof \BackedEnum
                ? $validated['gender']->value
                : $validated['gender'],
            'phone' => $validated['phone'],
            'privacy_policy_version' => $validated['privacy_policy_version'],
        ];

        $pending = PendingRegistration::query()->create([
            'email' => $email,
            'password_hash' => Hash::make((string) $validated['password']),
            'payload' => $payload,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'resend_count' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'last_sent_at' => now(),
            'intended_url' => $intendedUrl,
            'consumed_at' => null,
        ]);

        $this->sendCodeNotification($email, $code);
        $request->session()->put(self::SESSION_KEY, $pending->id);

        return $pending;
    }

    public function findActiveForSession(Request $request): ?PendingRegistration
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (! is_string($id) || $id === '') {
            return null;
        }

        $pending = PendingRegistration::query()->find($id);

        if ($pending === null || $pending->isConsumed()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $pending;
    }

    /**
     * @return string 'sent' | 'cooldown' | 'max_resends' | 'expired' | 'consumed' | 'not_found'
     */
    public function resend(PendingRegistration $pending): string
    {
        if ($pending->isConsumed()) {
            return 'consumed';
        }

        if ($pending->isExpired()) {
            return 'expired';
        }

        if ($pending->resend_count >= self::MAX_RESENDS) {
            return 'max_resends';
        }

        if (
            $pending->last_sent_at !== null
            && $pending->last_sent_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
        ) {
            return 'cooldown';
        }

        $code = $this->generateCode();

        $pending->forceFill([
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'resend_count' => $pending->resend_count + 1,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'last_sent_at' => now(),
        ])->save();

        $this->sendCodeNotification($pending->email, $code);

        return 'sent';
    }

    /**
     * @return User|string User on success, otherwise result code.
     */
    public function verify(PendingRegistration $pending, string $code, Request $request): User|string
    {
        try {
            $result = DB::transaction(function () use ($pending, $code, $request): User|string|array {
                /** @var PendingRegistration|null $locked */
                $locked = PendingRegistration::query()
                    ->whereKey($pending->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || $locked->isConsumed()) {
                    return 'not_found';
                }

                if ($locked->isExpired()) {
                    $locked->forceFill([
                        'code_hash' => Hash::make($this->generateCode()),
                        'attempts' => self::MAX_ATTEMPTS,
                    ])->save();

                    return 'expired';
                }

                if ($locked->attempts >= self::MAX_ATTEMPTS) {
                    return 'too_many_attempts';
                }

                if (! Hash::check($code, $locked->code_hash)) {
                    $locked->increment('attempts');

                    if ($locked->fresh()?->attempts >= self::MAX_ATTEMPTS) {
                        return 'too_many_attempts';
                    }

                    return 'invalid';
                }

                if ($this->emailExists($locked->email)) {
                    $locked->forceFill(['consumed_at' => now()])->save();

                    return 'email_in_use';
                }

                $policy = PrivacyPolicyService::active();

                if ($policy === null) {
                    return 'policy_unavailable';
                }

                $payload = is_array($locked->payload) ? $locked->payload : [];
                $payloadVersion = (string) ($payload['privacy_policy_version'] ?? '');

                if ($payloadVersion !== $policy->version) {
                    return 'policy_stale';
                }

                $user = $this->createVerifiedUser($locked, $policy, $request);

                $locked->forceFill([
                    'consumed_at' => now(),
                    'code_hash' => Hash::make($this->generateCode()),
                    'attempts' => self::MAX_ATTEMPTS,
                ])->save();

                return [
                    'user' => $user,
                    'intended_url' => $locked->intended_url,
                ];
            });

            if (is_string($result)) {
                if ($result === 'email_in_use') {
                    $request->session()->forget(self::SESSION_KEY);
                }

                return $result;
            }

            /** @var User $user */
            $user = $result['user'];
            $intendedUrl = $result['intended_url'] ?? null;

            $request->session()->forget(self::SESSION_KEY);

            if (is_string($intendedUrl) && $intendedUrl !== '') {
                $safe = SafeLoginReturnUrl::sanitize($intendedUrl);
                if ($safe !== null) {
                    $request->session()->put(SafeLoginReturnUrl::SESSION_KEY, $safe);
                }
            }

            UserActivityLogger::logAccountCreated($user);
            UserActivityLogger::logEmailVerified($user);

            $request->session()->put('auth.skip_login_otp', true);
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('otp_verified', true);

            event(new Verified($user));

            return $user;
        } catch (UniqueConstraintViolationException) {
            Log::warning('signup.verify.unique_conflict', [
                'pending_id' => $pending->id,
            ]);

            return 'email_in_use';
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                return 'duplicate_identity';
            }

            throw $exception;
        } catch (QueryException $exception) {
            Log::error('signup.verify.query_failed', [
                'pending_id' => $pending->id,
                'sql_state' => $exception->errorInfo[0] ?? null,
            ]);

            return 'create_failed';
        } catch (Throwable $exception) {
            Log::error('signup.verify.failed', [
                'pending_id' => $pending->id,
                'exception' => $exception::class,
            ]);

            return 'create_failed';
        }
    }

    public function invalidateSessionPending(Request $request): void
    {
        $id = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($id) || $id === '') {
            return;
        }

        PendingRegistration::query()
            ->whereKey($id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'code_hash' => Hash::make($this->generateCode()),
                'attempts' => self::MAX_ATTEMPTS,
            ]);
    }

    public function invalidateActiveForEmail(string $normalizedEmail): void
    {
        PendingRegistration::query()
            ->where('email', $normalizedEmail)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'code_hash' => Hash::make($this->generateCode()),
                'attempts' => self::MAX_ATTEMPTS,
            ]);
    }

    public function purgeExpired(): int
    {
        return PendingRegistration::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('consumed_at');
            })
            ->where('updated_at', '<', now()->subDay())
            ->delete();
    }

    private function createVerifiedUser(
        PendingRegistration $pending,
        PrivacyPolicyVersion $policy,
        Request $request,
    ): User {
        $payload = is_array($pending->payload) ? $pending->payload : [];

        $data = [
            ...$payload,
            'email' => $pending->email,
            'password_hash' => $pending->password_hash,
            'email_verified_at' => now(),
        ];

        return $this->registrationService->register($data, $policy, $request);
    }

    private function sendCodeNotification(string $email, string $code): void
    {
        Notification::route('mail', $email)
            ->notify(new SignupEmailVerificationCode($code, self::EXPIRES_MINUTES));
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
