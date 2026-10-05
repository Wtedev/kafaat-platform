<?php

namespace App\Services\Audit;

use App\Enums\AuditLogResult;
use App\Models\User;
use Illuminate\Http\Request;

final class StaffAccountAudit
{
    /**
     * @param  list<string>  $fields
     */
    public static function recordUpdate(User $actor, User $target, array $fields, ?Request $request = null): void
    {
        $fields = array_values(array_unique($fields));
        sort($fields);

        if ($fields === []) {
            return;
        }

        app(AuditLogger::class)->recordOrFail(
            $actor,
            'staff.updated',
            AuditLogResult::Success,
            $target,
            metadata: ['fields' => $fields],
            request: $request,
        );
    }

    public static function recordPasswordReset(User $actor, User $target, ?Request $request = null): void
    {
        app(AuditLogger::class)->recordOrFail(
            $actor,
            'staff.password_reset_sent',
            AuditLogResult::Success,
            $target,
            request: $request,
        );
    }
}
