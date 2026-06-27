<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class RegistrationRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::RegistrationsRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        // Registrations and attendance remain linked to the anonymized user row.
        // Operational access is restricted via User::scopeOperational() and policies.
    }
}
