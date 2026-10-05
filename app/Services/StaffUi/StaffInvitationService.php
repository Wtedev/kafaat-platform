<?php

namespace App\Services\StaffUi;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Rbac\RbacCatalog;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StaffInvitationService
{
    public const MARKER = 'staff-invite:';

    /** @var list<string> */
    public const ROLES = [
        RbacCatalog::ROLE_ADMIN,
        RbacCatalog::ROLE_STAFF,
    ];

    public function isPending(User $user): bool
    {
        return ! $user->is_active
            && is_string($user->remember_token)
            && str_starts_with($user->remember_token, self::MARKER);
    }

    public function invite(string $name, string $email, string $role): User
    {
        $email = EmailNormalizer::normalize($email);

        if (User::query()->whereEmailIgnoreCase($email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني مستخدم بالفعل.',
            ]);
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::password(40),
            'role_type' => $role,
            'is_active' => false,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => null,
        ]);
        $user->forceFill([
            'remember_token' => self::MARKER.Str::random(40),
        ])->save();
        $user->syncRoles([$role]);
        $this->sendLink($user);

        return $user->fresh();
    }

    public function resend(User $user): void
    {
        if (! $this->isPending($user)) {
            abort(422);
        }

        $createdAt = DB::table('password_reset_tokens')->where('email', $user->email)->value('created_at');
        $throttle = (int) config('auth.passwords.users.throttle', 60);
        if ($createdAt !== null && now()->diffInSeconds(Carbon::parse($createdAt), true) < $throttle) {
            throw ValidationException::withMessages([
                'invitation' => 'انتظر دقيقة قبل إعادة إرسال الدعوة.',
            ]);
        }

        $this->sendLink($user);
    }

    public function cancel(User $user): void
    {
        if (! $this->isPending($user)) {
            abort(422);
        }

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function accepting(User $user): bool
    {
        return $this->isPending($user);
    }

    public function sendLink(User $user): void
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new StaffInvitationNotification($token));
    }
}
