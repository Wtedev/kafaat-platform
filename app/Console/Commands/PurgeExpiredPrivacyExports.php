<?php

namespace App\Console\Commands;

use App\Enums\PrivacyExportFileStatus;
use App\Models\PrivacyExportFile;
use App\Services\Audit\AuditLogger;
use App\Enums\AuditLogResult;
use App\Services\Privacy\Export\PersonalDataExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeExpiredPrivacyExports extends Command
{
    protected $signature = 'privacy:purge-expired-exports
                            {--dry-run : Report without deleting files}
                            {--batch=100 : Number of records per batch}';

    protected $description = 'Delete expired personal data export ZIP files from private storage';

    public function handle(PersonalDataExportService $exportService, AuditLogger $auditLogger): int
    {
        $lock = Cache::lock('privacy:purge-expired-exports', 600);
        if (! $lock->get()) {
            $this->warn('Purge already running.');

            return self::SUCCESS;
        }

        try {
            $dryRun = (bool) $this->option('dry-run');
            $batch = max(1, (int) $this->option('batch'));
            $purged = 0;
            $scanned = 0;

            PrivacyExportFile::query()
                ->where('status', PrivacyExportFileStatus::Ready->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->chunkById($batch, function ($exports) use ($dryRun, $exportService, &$purged, &$scanned): void {
                    foreach ($exports as $export) {
                        $scanned++;
                        if ($dryRun) {
                            continue;
                        }

                        if ($exportService->purgeExpiredExport($export)) {
                            $purged++;
                        }
                    }
                });

            if (! $dryRun && $purged > 0) {
                $auditLogger->recordOrFail(
                    null,
                    'privacy_export.expired',
                    AuditLogResult::Success,
                    null,
                    metadata: [
                        'purged_count' => $purged,
                        'scanned_count' => $scanned,
                    ],
                );
            }

            $this->info(sprintf(
                'Scanned: %d | Purged: %d%s',
                $scanned,
                $purged,
                $dryRun ? ' (dry-run)' : '',
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
