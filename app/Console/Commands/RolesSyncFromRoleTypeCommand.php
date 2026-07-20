<?php

namespace App\Console\Commands;

use App\Services\Rbac\RoleTypeSpatieSyncService;
use Illuminate\Console\Command;

class RolesSyncFromRoleTypeCommand extends Command
{
    protected $signature = 'roles:sync-from-role-type
                            {--apply : Persist changes (default is dry-run)}
                            {--no-enforce-single-admin : Skip demoting extra admins to staff}
                            {--show=30 : Max planned changes to print}';

    protected $description = 'Sync Spatie roles from users.role_type (Spatie becomes SoT; dual-writes normalized role_type). Default: dry-run.';

    public function handle(RoleTypeSpatieSyncService $sync): int
    {
        $dryRun = ! (bool) $this->option('apply');
        $enforceSingleAdmin = ! (bool) $this->option('no-enforce-single-admin');
        $show = max(0, (int) $this->option('show'));

        if ($dryRun) {
            $this->warn('Dry Run — no database writes. Pass --apply to persist.');
        } else {
            $this->warn('APPLY mode — writing Spatie roles + dual-writing role_type. Audit: storage/logs/role-sync.log');
        }

        $summary = $sync->syncFromRoleType($dryRun, $enforceSingleAdmin);

        $this->info(sprintf(
            '[%s] scanned=%d changed=%d skipped=%d demoted_extra_admins=%d',
            $summary['mode'],
            $summary['scanned'],
            $summary['changed'],
            $summary['skipped'],
            $summary['demoted_extra_admins'],
        ));

        $rows = collect($summary['changes'])->take($show === 0 ? null : $show);
        if ($rows->isNotEmpty()) {
            $this->table(
                ['user_id', 'email', 'from_spatie', 'to_spatie', 'from_role_type', 'to_role_type'],
                $rows->map(fn (array $c) => [
                    $c['user_id'],
                    $c['email'],
                    $c['from_spatie'] ?? '—',
                    $c['to_spatie'],
                    $c['from_role_type'] ?? '—',
                    $c['to_role_type'],
                ])->all(),
            );

            if ($show > 0 && count($summary['changes']) > $show) {
                $this->comment(sprintf('… and %d more (see role-sync.log)', count($summary['changes']) - $show));
            }
        }

        if (! $dryRun) {
            $drift = $sync->reportDrift();
            $this->info(sprintf('Post-apply drift count: %d', $drift['drift_count']));
            if ($drift['drift_count'] > 0) {
                $this->warn('Remaining drift usually means empty/unknown role_type with no Spatie role (or vice versa). Inspect with roles:report-drift.');
            }
        }

        return self::SUCCESS;
    }
}
