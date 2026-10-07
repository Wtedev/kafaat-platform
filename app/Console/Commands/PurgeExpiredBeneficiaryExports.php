<?php

namespace App\Console\Commands;

use App\Services\StaffUi\StaffBeneficiaryExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeExpiredBeneficiaryExports extends Command
{
    protected $signature = 'staff-ui:purge-expired-beneficiary-exports
                            {--dry-run : Report without deleting files}';

    protected $description = 'Delete queued beneficiary Excel exports older than 24 hours';

    public function handle(StaffBeneficiaryExport $exports): int
    {
        $lock = Cache::lock('staff-ui:purge-expired-beneficiary-exports', 600);
        if (! $lock->get()) {
            $this->warn('Purge already running.');

            return self::SUCCESS;
        }

        try {
            $dryRun = (bool) $this->option('dry-run');
            $deleted = $exports->purgeExpired($dryRun);

            $this->info(sprintf(
                'Purged: %d%s',
                $deleted,
                $dryRun ? ' (dry-run)' : '',
            ));

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
