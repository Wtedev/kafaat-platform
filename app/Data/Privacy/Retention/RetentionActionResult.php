<?php

namespace App\Data\Privacy\Retention;

use App\Enums\RetentionRunItemStatus;

final readonly class RetentionActionResult
{
    public function __construct(
        public RetentionRunItemStatus $status,
        public ?string $failureCode = null,
    ) {}

    public static function succeeded(): self
    {
        return new self(RetentionRunItemStatus::Succeeded);
    }

    public static function skipped(?string $reasonCode = null): self
    {
        return new self(RetentionRunItemStatus::Skipped, $reasonCode);
    }

    public static function failed(string $failureCode): self
    {
        return new self(RetentionRunItemStatus::Failed, $failureCode);
    }
}
