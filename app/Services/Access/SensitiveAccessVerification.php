<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SensitiveAccessVerification
{
    public const SESSION_KEY = 'sensitive_access_verified_at';

    public static function ttlSeconds(): int
    {
        return max(60, (int) config('access_control.sensitive_reverify_ttl_seconds', 300));
    }

    public static function markVerified(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
    }

    public static function isRecentlyVerified(Request $request): bool
    {
        $verifiedAt = $request->session()->get(self::SESSION_KEY);

        if (! is_int($verifiedAt) && ! is_numeric($verifiedAt)) {
            return false;
        }

        return now()->timestamp - (int) $verifiedAt <= self::ttlSeconds();
    }

    /**
     * @throws ValidationException
     */
    public static function assertPasswordConfirmed(User $actor, string $password): void
    {
        if (! Hash::check($password, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }
    }
}
