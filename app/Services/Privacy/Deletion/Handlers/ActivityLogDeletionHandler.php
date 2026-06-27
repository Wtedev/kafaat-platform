<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\UserActivityLog;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class ActivityLogDeletionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::ActivityLogs->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        UserActivityLog::query()
            ->where('user_id', $context->target->id)
            ->delete();
    }
}
