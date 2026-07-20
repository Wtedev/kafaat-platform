<?php

namespace App\Console\Commands;

use App\Services\Rbac\RoleTypeSpatieSyncService;
use Illuminate\Console\Command;

class RolesSyncToRoleTypeCommand extends Command
{
    protected $signature = 'roles:sync-to-role-type
                            {--apply : Persist changes (default is dry-run)}
                            {--show=30 : Max planned changes to print}';

    protected $description = 'Rollback companion: rewrite users.role_type from Spatie application roles. Does not touch permissions. Default: dry-run.';

    public function handle(RoleTypeSpatieSyncService $sync): int
    {
        $dryRun = ! (bool) $this->option('apply');
        $show = max(0, (int) $this->option('show'));

        if ($dryRun) {
            $this->warn('Dry Run — no database writes. Pass --apply to persist.');
        } else {
            $this->warn('APPLY mode — rewriting role_type from Spatie. Audit: storage/logs/role-sync.log');
        }

        $summary = $sync->syncToRoleType($dryRun);

        $this->info(sprintf(
            '[%s] scanned=%d changed=%d skipped=%d',
            $summary['mode'],
            $summary['scanned'],
            $summary['changed'],
            $summary['skipped'],
        ));

        $rows = collect($summary['changes'])->take($show === 0 ? null : $show);
        if ($rows->isNotEmpty()) {
            $this->table(
                ['user_id', 'email', 'spatie', 'from_role_type', 'to_role_type'],
                $rows->map(fn (array $c) => [
                    $c['user_id'],
                    $c['email'],
                    $c['spatie_role'],
                    $c['from_role_type'] ?? '—',
                    $c['to_role_type'],
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
