<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'system:health {--json : Output JSON only}';

    protected $description = 'Check production health without exposing secrets or PII';

    public function handle(SystemHealthService $health): int
    {
        $report = $health->check();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
        }

        $this->info('System health: '.$report['status']);

        foreach ($report['checks'] as $name => $check) {
            $status = $check['status'] ?? 'unknown';
            $suffix = isset($check['message']) ? ' ('.$check['message'].')' : '';
            if (isset($check['count'])) {
                $suffix .= ' count='.$check['count'];
            }
            if (isset($check['pending'])) {
                $suffix .= ' pending='.$check['pending'];
            }
            if (isset($check['violations']) && $check['violations'] !== []) {
                $suffix .= ' violations='.count($check['violations']);
            }
            $this->line(sprintf('  [%s] %s%s', $status, $name, $suffix));
        }

        if (isset($report['checks']['production_config']['violations'])) {
            foreach ($report['checks']['production_config']['violations'] as $violation) {
                $this->warn('  - '.$violation);
            }
        }

        return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
