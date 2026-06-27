<?php

namespace App\Services\Operations;

use App\Enums\RetentionRunMode;
use App\Enums\RetentionRunStatus;
use App\Models\RetentionRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SystemHealthService
{
    public function __construct(
        private readonly ProductionEnvironmentValidator $productionValidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'private_disk' => $this->checkPrivateDisk(),
            'migrations' => $this->checkMigrations(),
            'scheduler' => $this->checkScheduler(),
            'failed_jobs' => $this->checkFailedJobs(),
            'production_config' => $this->checkProductionConfig(),
        ];

        $healthy = collect($checks)->every(fn (array $check): bool => ($check['status'] ?? 'fail') !== 'fail');

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'fail', 'message' => 'database_unreachable'];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'health:probe:'.uniqid('', true);
            Cache::put($key, '1', 10);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return $ok ? ['status' => 'ok'] : ['status' => 'fail', 'message' => 'cache_write_failed'];
        } catch (\Throwable) {
            return ['status' => 'fail', 'message' => 'cache_unreachable'];
        }
    }

    /**
     * @return array{status: string, message?: string, connection?: string}
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default');

        try {
            Queue::connection($connection)->size();

            return ['status' => 'ok', 'connection' => $connection];
        } catch (\Throwable) {
            return ['status' => 'warn', 'message' => 'queue_size_unavailable', 'connection' => $connection];
        }
    }

    /**
     * @return array{status: string, message?: string}
     */
    private function checkPrivateDisk(): array
    {
        $diskName = (string) config('privacy.export.disk', 'private_documents');

        try {
            $disk = Storage::disk($diskName);
            $probe = '.health-probe/'.uniqid('', true).'.txt';
            $disk->put($probe, 'ok');
            $exists = $disk->exists($probe);
            $disk->delete($probe);

            return $exists ? ['status' => 'ok'] : ['status' => 'fail', 'message' => 'private_disk_probe_failed'];
        } catch (\Throwable) {
            return ['status' => 'fail', 'message' => 'private_disk_unreachable'];
        }
    }

    /**
     * @return array{status: string, pending?: int}
     */
    private function checkMigrations(): array
    {
        try {
            $pending = count(app('migrator')->pendingMigrations());

            return [
                'status' => $pending === 0 ? 'ok' : 'warn',
                'pending' => $pending,
            ];
        } catch (\Throwable) {
            return ['status' => 'warn', 'pending' => -1];
        }
    }

    /**
     * @return array{status: string, last_execute_at?: string|null}
     */
    private function checkScheduler(): array
    {
        if (! Schema::hasTable('retention_runs')) {
            return ['status' => 'warn', 'last_execute_at' => null];
        }

        $last = RetentionRun::query()
            ->where('mode', RetentionRunMode::Execute)
            ->whereIn('status', [RetentionRunStatus::Completed, RetentionRunStatus::CompletedWithFailures])
            ->latest('completed_at')
            ->value('completed_at');

        if ($last === null) {
            return ['status' => 'warn', 'last_execute_at' => null];
        }

        $staleHours = (int) config('security.health.scheduler_stale_hours', 48);
        $isStale = $last->lt(now()->subHours($staleHours));

        return [
            'status' => $isStale ? 'warn' : 'ok',
            'last_execute_at' => $last->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, count?: int}
     */
    private function checkFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['status' => 'warn', 'count' => 0];
        }

        $count = (int) DB::table('failed_jobs')->count();
        $threshold = (int) config('security.health.failed_jobs_warning_threshold', 10);

        return [
            'status' => $count >= $threshold ? 'warn' : 'ok',
            'count' => $count,
        ];
    }

    /**
     * @return array{status: string, violations?: list<string>}
     */
    private function checkProductionConfig(): array
    {
        $violations = $this->productionValidator->violations();

        return [
            'status' => $violations === [] ? 'ok' : 'fail',
            'violations' => $violations,
        ];
    }
}
