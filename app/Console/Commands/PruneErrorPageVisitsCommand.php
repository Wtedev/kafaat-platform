<?php

namespace App\Console\Commands;

use App\Models\ErrorPageVisit;
use App\Services\Operations\ErrorPageVisitRecorder;
use Illuminate\Console\Command;

class PruneErrorPageVisitsCommand extends Command
{
    protected $signature = 'error-pages:prune
                            {--days=90 : Delete visits older than this many days}
                            {--dry-run : Report how many rows would be deleted without deleting}';

    protected $description = 'Delete old error page visit statistics rows';

    public function handle(ErrorPageVisitRecorder $recorder): int
    {
        $days = max(1, (int) $this->option('days'));

        if ($this->option('dry-run')) {
            $cutoff = now()->subDays($days);
            $count = ErrorPageVisit::query()
                ->where('created_at', '<', $cutoff)
                ->count();

            $this->info("Would delete {$count} visit(s) older than {$days} day(s).");

            return self::SUCCESS;
        }

        $deleted = $recorder->pruneOlderThan($days);
        $this->info("Deleted {$deleted} visit(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
