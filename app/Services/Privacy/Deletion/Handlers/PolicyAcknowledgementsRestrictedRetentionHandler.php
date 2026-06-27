<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class PolicyAcknowledgementsRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::PolicyAcknowledgementsRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        PrivacyPolicyAcknowledgement::query()
            ->where('user_id', $context->target->id)
            ->update([
                'ip_address' => null,
                'user_agent' => null,
            ]);
    }
}
