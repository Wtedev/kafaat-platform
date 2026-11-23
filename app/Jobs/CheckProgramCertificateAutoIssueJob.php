<?php

namespace App\Jobs;

use App\Services\Certificates\ProgramCertificateAutoIssuer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckProgramCertificateAutoIssueJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $uniqueFor = 60;

    public function __construct(
        public readonly int $registrationId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'program-certificate-auto:'.$this->registrationId;
    }

    public function handle(ProgramCertificateAutoIssuer $autoIssuer): void
    {
        $autoIssuer->checkRegistration($this->registrationId);
    }
}
