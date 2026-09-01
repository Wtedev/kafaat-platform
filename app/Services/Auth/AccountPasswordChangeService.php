<?php

namespace App\Services\Auth;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\User;
use App\Services\Security\SecurityLogService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class AccountPasswordChangeService
{
    public const MSG_CURRENT_WRONG = 'كلمة المرور الحالية غير صحيحة.';

    public const MSG_SAME_PASSWORD = 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.';

    public const MSG_SUCCESS = 'تم تحديث كلمة المرور بنجاح.';

    public function __construct(
        private readonly SecurityLogService $securityLogService,
    ) {}

    /**
     * @return list<string|Password>
     */
    public static function newPasswordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)];
    }

    /**
     * Change password for the authenticated web guard user.
     *
     * Order of operations (inside a DB transaction):
     * 1. Hash::check current password (before transaction; no secrets logged).
     * 2. Auth::guard('web')->logoutOtherDevices($currentPassword) — invalidates other
     *    database sessions using the *current* password hash while it is still valid.
     * 3. Persist new password (hashed cast) and rotate remember_token.
     * 4. Delete remaining database session rows for this user except the current session id
     *    (only when session.driver=database and the sessions table exists).
     * 5. Refresh the password hash AuthenticateSession keeps in the current session, so the
     *    session that performed the change is not logged out on its next request.
     */
    public function change(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $currentSessionId = null,
        string $guard = 'web',
    ): void {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            $this->log('auth.password_change_failed', SecurityLogResult::Failed, $user);

            throw ValidationException::withMessages([
                'current_password' => self::MSG_CURRENT_WRONG,
            ]);
        }

        if (Hash::check($newPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => self::MSG_SAME_PASSWORD,
            ]);
        }

        DB::transaction(function () use ($user, $currentPassword, $newPassword, $currentSessionId, $guard): void {
            Auth::guard($guard)->logoutOtherDevices($currentPassword);

            $user->forceFill([
                'password' => $newPassword,
                'remember_token' => Str::random(60),
            ])->save();

            $this->deleteOtherDatabaseSessions($user, $currentSessionId);
        });

        $this->refreshCurrentSessionPasswordHash($user, $guard);

        $this->log('auth.password_change_completed', SecurityLogResult::Success, $user);
    }

    /**
     * AuthenticateSession only refreshes its stored hash in its own middleware tail, which
     * does not run for Livewire update requests. Without this the current session would be
     * logged out on the next panel request even though its session row was preserved.
     */
    private function refreshCurrentSessionPasswordHash(User $user, string $guard): void
    {
        $sessionGuard = Auth::guard($guard);

        if (! $sessionGuard instanceof SessionGuard) {
            return;
        }

        if ($sessionGuard->id() === null || (string) $sessionGuard->id() !== (string) $user->getAuthIdentifier()) {
            return;
        }

        $session = Session::driver();

        if (! $session->isStarted()) {
            return;
        }

        $session->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $sessionGuard->hashPasswordForCookie($user->getAuthPassword()),
        );
    }

    private function deleteOtherDatabaseSessions(User $user, ?string $exceptSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        if (! Schema::hasTable('sessions')) {
            return;
        }

        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    private function log(string $event, SecurityLogResult $result, User $user): void
    {
        $this->securityLogService->record(
            $event,
            $result,
            $result === SecurityLogResult::Success
                ? SecurityLogSeverity::Info
                : SecurityLogSeverity::Warning,
            $user,
            request: request(),
        );
    }
}
