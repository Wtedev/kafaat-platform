<?php

namespace App\Services\Privacy\Deletion\Contracts;

use App\Data\Privacy\DeletionExecutionContext;

interface DeletionHandlerInterface
{
    public function name(): string;

    public function handle(DeletionExecutionContext $context): void;
}
