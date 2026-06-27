<?php

namespace App\Console\Commands;

use App\Models\RetentionPolicy;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeExpiredPrivacyExports extends Command
{
    protected $signature = 'privacy:purge-expired-exports
                            {--dry-run : Report without deleting files}
                            {--batch=100 : Number of records per batch}';

    protected $description = 'Delete expired personal data export ZIP files via retention engine';

    public function handle(RetentionPolicyEngine $engine): int
    {
        $lock = Cache::lock('privacy:purge-expired-exports', 600);
        if (! $lock->get()) {
            $this->warn('Purge already running.');

            return self::SUCCESS;
        }

        try {
            $policy = RetentionPolicy::query()
                ->where('resource_type', 'privacy_export_files')
                ->where('status', 'active')
                ->first();

            if ($policy === null) {
                $this->warn('No active retention policy for privacy export files. Nothing purged.');

                return self::SUCCESS;
            }

            $dryRun = (bool) $this->option('dry-run');
            $batch = max(1, (int) $this->option('batch'));

            $run = $engine->execute(
                policy: $policy,
                batchSize: $batch,
                dryRun: $dryRun,
            );

            $this->info(sprintf(
                'Scanned: %d | Purged: %d | Failed: %d%s',
                $run->eligible_count,
                $run->succeeded_count,
                $run->failed_count,
                $dryRun ? ' (dry-run)' : '',
            ));

            return $run->failed_count > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
