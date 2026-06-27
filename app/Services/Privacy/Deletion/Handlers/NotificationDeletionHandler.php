<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\InboxNotification;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class NotificationDeletionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::Notifications->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        InboxNotification::query()
            ->where('user_id', $context->target->id)
            ->delete();
    }
}
