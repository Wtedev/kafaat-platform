<?php

namespace App\Services\StaffUi;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Rbac\RbacCatalog;
use App\Support\Auth\EmailNormalizer;
use App\Support\Privacy\UserDeletionGuard;
use App\Support\UserAccountRoleForm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StaffInvitationService
{
    public const BROKER = 'staff_invitations';

    /** @var list<string> */
    public const ROLES = [
        RbacCatalog::ROLE_ADMIN,
        RbacCatalog::ROLE_STAFF,
    ];

    /**
     * Domain rows that belong to the person. Role pivots, sessions, and reset tokens are the account itself.
     *
     * @var array<string, string>
     */
    private const LINKED_RECORDS = [
        'profiles' => 'user_id',
        'program_registrations' => 'user_id',
        'path_registrations' => 'user_id',
        'volunteer_registrations' => 'user_id',
        'volunteer_hours' => 'user_id',
        'certificates' => 'user_id',
        'user_documents' => 'user_id',
        'user_course_progress' => 'user_id',
        'profile_recommendations' => 'user_id',
        'team_members' => 'user_id',
        'training_program_editors' => 'user_id',
        'learning_path_editors' => 'user_id',
        'in_app_notifications' => 'user_id',
        'user_activity_logs' => 'user_id',
        'privacy_policy_acknowledgements' => 'user_id',
        'candidate_pool_preferences' => 'user_id',
        'candidate_pool_consent_events' => 'user_id',
        'privacy_requests' => 'user_id',
        'support_tickets' => 'user_id',
        'audit_logs' => 'target_user_id',
    ];

    public function isPending(User $user): bool
    {
        return $user->invited_at !== null && ! $user->is_active;
    }

    public function requiresReinvite(User $user): bool
    {
        return ! $user->is_active
            && ! $this->isPending($user)
            && $user->last_login_at === null
            && $user->email_verified_at === null
            && $this->hasLinkedRecords($user);
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
            'invited_at' => now(),
        ]);
        $user->syncRoles([$role]);
        UserAccountRoleForm::applyRoleSideEffects($user, $role);
        $this->sendLink($user);

        return $user->fresh();
    }

    public function resend(User $user): void
    {
        if (! $this->isPending($user)) {
            abort(422);
        }

        $this->assertResendThrottle($user);
        $this->sendLink($user);
    }

    public function reopen(User $user): void
    {
        if (! $this->requiresReinvite($user)) {
            abort(422);
        }

        $this->assertResendThrottle($user);
        $user->forceFill([
            'invited_at' => now(),
            'is_active' => false,
        ])->save();
        $this->sendLink($user);
    }

    /**
     * @return bool True when the unused account was deleted.
     */
    public function cancel(User $user, User $actor, ?Request $request = null): bool
    {
        if (! $this->isPending($user)) {
            abort(422);
        }

        $email = (string) $user->email;
        $userId = $user->id;
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $deleted = $user->last_login_at === null && ! $this->hasLinkedRecords($user);

        if ($deleted) {
            UserDeletionGuard::runAuthorized(function () use ($user): void {
                $user->roles()->detach();
                $user->permissions()->detach();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            });
        } else {
            $user->forceFill([
                'invited_at' => null,
            ])->save();
        }

        app(AuditLogger::class)->recordOrFail(
            $actor,
            'staff.invitation_cancelled',
            AuditLogResult::Success,
            $deleted ? null : $user,
            metadata: [
                'email' => $email,
                'user_id' => $userId,
                'deleted' => $deleted,
            ],
            request: $request,
        );

        return $deleted;
    }

    public function accepting(User $user): bool
    {
        return $this->isPending($user);
    }

    public function sendLink(User $user): void
    {
        $token = Password::broker(self::BROKER)->createToken($user);
        $user->notify(new StaffInvitationNotification($token));
    }

    public function hasLinkedRecords(User $user): bool
    {
        foreach (self::LINKED_RECORDS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, $user->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function assertResendThrottle(User $user): void
    {
        $createdAt = DB::table('password_reset_tokens')->where('email', $user->email)->value('created_at');
        $throttle = (int) config('auth.passwords.'.self::BROKER.'.throttle', 60);
        if ($createdAt !== null && now()->diffInSeconds(Carbon::parse($createdAt), true) < $throttle) {
            throw ValidationException::withMessages([
                'invitation' => 'انتظر دقيقة قبل إعادة إرسال الدعوة.',
            ]);
        }
    }
}
