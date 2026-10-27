<?php

namespace App\Jobs;

use App\Services\Certificates\ProgramCertificateIssuance;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IssueProgramCertificateJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $registrationId,
        public readonly ?int $issuedById,
    ) {}

    public function uniqueId(): string
    {
        return 'program-certificate:'.$this->registrationId;
    }

    public function handle(ProgramCertificateIssuance $issuance): void
    {
        $issuance->issueRegistration($this->registrationId, $this->issuedById);
    }
}
