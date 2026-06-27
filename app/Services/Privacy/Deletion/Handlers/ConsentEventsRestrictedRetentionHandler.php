<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\CandidatePoolConsentEvent;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class ConsentEventsRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::ConsentEventsRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        CandidatePoolConsentEvent::query()
            ->where('user_id', $context->target->id)
            ->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }
}
