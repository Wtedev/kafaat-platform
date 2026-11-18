<?php

namespace App\Services\ExperienceHouses;

use App\Mail\ExperienceHouseAccessMail;
use App\Models\ExperienceHouseLoginToken;
use App\Models\ExperienceHouseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * الدخول بالبريد وكلمة المرور. رابط لمرة واحدة يُرسل للبريد لإنشاء كلمة المرور (دعوة) أو إعادة تعيينها.
 */
final class ManagerAccess
{
    public const SESSION_KEY = 'experience_houses.manager_id';

    public const LOGIN_MINUTES = 30;

    public const INVITE_DAYS = 7;

    public function current(Request $request): ?ExperienceHouseManager
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $manager = ExperienceHouseManager::query()->find((int) $id);

        return $manager && $manager->is_active ? $manager : null;
    }

    /**
     * يرسل رابط الدخول/التفعيل. يُرجع false إذا فشل الإرسال.
     */
    public function sendLink(ExperienceHouseManager $manager, string $purpose, ?ExperienceHouseManager $inviter = null): bool
    {
        $plain = Str::random(64);

        ExperienceHouseLoginToken::query()->create([
            'manager_id' => $manager->id,
            'token_hash' => hash('sha256', $plain),
            'purpose' => $purpose,
            'expires_at' => $purpose === 'invite' ? now()->addDays(self::INVITE_DAYS) : now()->addMinutes(self::LOGIN_MINUTES),
        ]);

        $url = route('experience-houses.manage.auth', ['token' => $plain]);

        try {
            Mail::to($manager->email)->send(new ExperienceHouseAccessMail($manager, $url, $purpose, $inviter));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * الرابط صالح؟ (بدون استهلاكه) — لعرض نموذج إنشاء كلمة المرور.
     */
    public function peek(string $plain): ?ExperienceHouseLoginToken
    {
        $token = ExperienceHouseLoginToken::query()
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        if (! $token || $token->used_at !== null || $token->expires_at->isPast()) {
            return null;
        }

        $manager = $token->manager;

        return $manager && $manager->is_active ? $token : null;
    }

    /**
     * يستهلك الرابط ويحفظ كلمة المرور الجديدة ويفعّل الحساب.
     */
    public function setPasswordWithToken(string $plain, string $password): ?ExperienceHouseManager
    {
        $token = $this->peek($plain);
        if (! $token) {
            return null;
        }

        $token->forceFill(['used_at' => now()])->save();

        $manager = $token->manager;
        $manager->forceFill([
            'password' => $password,
            'activated_at' => $manager->activated_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        return $manager;
    }

    public function attempt(string $email, string $password): ?ExperienceHouseManager
    {
        $manager = ExperienceHouseManager::query()
            ->where('email', mb_strtolower(trim($email)))
            ->where('is_active', true)
            ->first();

        if (! $manager || ! $manager->password || ! Hash::check($password, $manager->password)) {
            return null;
        }

        $manager->forceFill(['last_login_at' => now()])->save();

        return $manager;
    }

    public function login(Request $request, ExperienceHouseManager $manager): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $manager->id);
    }

    public function logout(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerateToken();
    }
}
