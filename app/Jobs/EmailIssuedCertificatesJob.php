<?php

namespace App\Jobs;

use App\Enums\CertificatePdfStatus;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmailIssuedCertificatesJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>|null  $certificateIds
     */
    public function __construct(
        public int $programId,
        public ?int $sentById,
        public ?array $certificateIds = null,
        public string $ownerClass = TrainingProgram::class,
    ) {
        $this->onQueue('certificates');
    }

    public function handle(CertificateService $certificates): void
    {
        $sentBy = $this->sentById !== null ? User::query()->find($this->sentById) : null;
        $query = Certificate::query()
            ->with(['user', 'certificateable'])
            ->where('certificateable_type', (new $this->ownerClass)->getMorphClass())
            ->where('certificateable_id', $this->programId)
            ->active()
            ->where('pdf_status', CertificatePdfStatus::Generated)
            ->whereNull('emailed_at');

        if ($this->certificateIds !== null) {
            $query->whereIn('id', $this->certificateIds);
        }

        $query->each(function (Certificate $certificate) use ($certificates, $sentBy): void {
            $certificates->emailCertificate($certificate, $sentBy);
        });
    }
}
