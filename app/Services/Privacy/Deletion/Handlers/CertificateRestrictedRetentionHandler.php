<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class CertificateRestrictedRetentionHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::CertificatesRetention->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        // Certificate rows and verification snapshots remain for public verification.
    }
}
