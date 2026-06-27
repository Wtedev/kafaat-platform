<?php

namespace App\Services\Privacy;

use App\Enums\AuditLogResult;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AccountDeactivationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function deactivate(
        User $target,
        User $actor,
        ?string $reason = null,
        ?Request $request = null,
    ): void {
        if ($target->isProtectedAdminUser()) {
            throw new AuthorizationException('Protected admin accounts cannot be deactivated.');
        }

        if (! $actor->can('deactivate', $target)) {
            throw new AuthorizationException('You are not allowed to deactivate this account.');
        }

        if (! $target->is_active) {
            return;
        }

        $target->update(['is_active' => false]);
        $this->invalidateSessions($target);

        $this->auditLogger->recordOrFail(
            $actor,
            'account.deactivated',
            AuditLogResult::Success,
            $target,
            reason: $reason,
            request: $request,
        );
    }

    public function invalidateSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        EmailVerificationCode::query()->where('user_id', $user->id)->delete();

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();
    }
}
