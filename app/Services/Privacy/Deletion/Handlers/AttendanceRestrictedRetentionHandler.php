<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class AttendanceRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::AttendanceRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        // Attendance rows remain via registration FK for restricted retention evidence.
    }
}
