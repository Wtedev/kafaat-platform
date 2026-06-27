<?php

namespace App\Jobs;

use App\Services\Privacy\Export\PersonalDataExportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeneratePersonalDataExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $timeout;

    public function __construct(
        public readonly int $privacyRequestId,
    ) {
        $this->tries = max(1, (int) config('privacy.export.job_tries', 3));
        $this->backoff = array_map('intval', config('privacy.export.job_backoff_seconds', [60, 300, 900]));
        $this->timeout = max(60, (int) config('privacy.export.job_timeout_seconds', 600));
    }

    public function uniqueId(): string
    {
        return 'privacy-export:'.$this->privacyRequestId;
    }

    public function handle(PersonalDataExportService $service): void
    {
        $service->generateForRequest($this->privacyRequestId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Privacy export job failed.', [
            'privacy_request_id' => $this->privacyRequestId,
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['privacy-export', 'privacy-request:'.$this->privacyRequestId];
    }
}
