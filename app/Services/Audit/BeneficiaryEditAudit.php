<?php

namespace App\Services\Audit;

use App\Enums\AuditLogResult;
use App\Models\User;
use Illuminate\Http\Request;

final class BeneficiaryEditAudit
{
    /**
     * @param  list<string>  $fields
     */
    public static function record(User $actor, User $target, array $fields, ?Request $request = null): void
    {
        $fields = array_values(array_unique($fields));
        sort($fields);

        if ($fields === []) {
            return;
        }

        app(AuditLogger::class)->recordOrFail(
            $actor,
            'beneficiary.updated',
            AuditLogResult::Success,
            $target,
            metadata: ['fields' => $fields],
            request: $request,
        );
    }
}
