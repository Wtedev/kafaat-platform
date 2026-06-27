<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\SecurityLog;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\DB;

final class SecurityLogRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::SecurityLogsRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        DB::table('security_logs')
            ->where('user_id', $context->target->id)
            ->update([
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'identifier_hash' => null,
            ]);
    }
}
