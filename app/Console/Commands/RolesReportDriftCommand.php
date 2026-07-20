<?php

namespace App\Console\Commands;

use App\Services\Rbac\RoleTypeSpatieSyncService;
use Illuminate\Console\Command;

class RolesReportDriftCommand extends Command
{
    protected $signature = 'roles:report-drift
                            {--limit=50 : Max drift rows to print (0 = all)}
                            {--json : Output JSON}';

    protected $description = 'Compare users.role_type vs Spatie application roles and report drift';

    public function handle(RoleTypeSpatieSyncService $sync): int
    {
        $limitOpt = (int) $this->option('limit');
        $printLimit = $limitOpt === 0 ? null : max(1, $limitOpt);

        $report = $sync->reportDrift($printLimit === null ? null : $printLimit);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $report['drift_count'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->info(sprintf(
            'Scanned %d users — drift: %d',
            $report['scanned'],
            $report['drift_count'],
        ));

        if ($report['by_kind'] !== []) {
            $this->table(['kind', 'count'], collect($report['by_kind'])->map(fn ($c, $k) => [$k, $c])->values()->all());
        }

        if ($report['entries'] !== []) {
            $this->table(
                ['user_id', 'email', 'role_type', 'spatie', 'expected_spatie', 'kind'],
                collect($report['entries'])->map(fn (array $e) => [
                    $e['user_id'],
                    $e['email'],
                    $e['role_type'] ?? '—',
                    $e['spatie_role'] ?? '—',
                    $e['expected_spatie'] ?? '—',
                    $e['kind'],
                ])->all(),
            );

            if ($printLimit !== null && $report['drift_count'] >= $printLimit) {
                $this->comment('Showing up to --limit rows. Re-run with --limit=0 for a full list.');
            }
        } else {
            $this->info('No drift — role_type and Spatie are aligned.');
        }

        return $report['drift_count'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
