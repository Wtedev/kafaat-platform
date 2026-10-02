<?php

namespace App\Jobs;

use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\Certificates\CertificateIssuanceService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

class IssueEligibleCertificatesJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $registrationId,
        public ?int $issuedById,
        public string $registrationClass = ProgramRegistration::class,
    ) {
        $this->onQueue('certificates');
    }

    public function handle(CertificateIssuanceService $issuance): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! is_a($this->registrationClass, Model::class, true)) {
            return;
        }

        $registration = $this->registrationClass::query()->find($this->registrationId);
        if (! $registration instanceof Model) {
            return;
        }

        $issuedBy = $this->issuedById !== null ? User::query()->find($this->issuedById) : null;
        $issuance->issueForRegistration($registration, $issuedBy);
    }
}
