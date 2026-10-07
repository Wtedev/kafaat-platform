<?php

namespace App\Jobs;

use App\Services\StaffUi\StaffBeneficiaryExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportStaffBeneficiaryProfiles implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $columnKeys
     */
    public function __construct(
        public readonly int $actorId,
        public readonly string $search,
        public readonly string $status,
        public readonly string $completeness,
        public readonly array $columnKeys,
    ) {}

    public function handle(StaffBeneficiaryExport $export): void
    {
        $export->storeForActor(
            $this->actorId,
            $this->search,
            $this->status,
            $this->completeness,
            $this->columnKeys,
        );
    }

    public function failed(?Throwable $exception): void
    {
        app(StaffBeneficiaryExport::class)->notifyFailure($this->actorId);
    }
}
