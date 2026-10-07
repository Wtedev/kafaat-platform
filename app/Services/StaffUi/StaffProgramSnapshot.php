<?php

namespace App\Services\StaffUi;

final class StaffProgramSnapshot
{
    public function __construct(
        public readonly string $registrationKey,
        public readonly string $registrationLabel,
        public readonly string $registrationTone,
        public readonly string $programLabel,
        public readonly string $programTone,
        public readonly ?string $publicationLine,
        public readonly int $pendingCount,
        public readonly string $acceptedLabel,
    ) {}
}
