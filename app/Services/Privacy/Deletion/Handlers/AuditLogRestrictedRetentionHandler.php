<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\AuditLog;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\DB;

final class AuditLogRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::AuditLogsRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        DB::table('audit_logs')
            ->where('target_user_id', $context->target->id)
            ->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }
}
