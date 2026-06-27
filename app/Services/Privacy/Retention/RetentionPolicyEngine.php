<?php

namespace App\Services\Privacy\Retention;

use App\Enums\AuditLogResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionPolicyStatus;
use App\Enums\RetentionRunItemStatus;
use App\Enums\RetentionRunMode;
use App\Enums\RetentionRunStatus;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\RetentionRunItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\Retention\Contracts\RetentionResourceHandler;
use App\Services\Security\SecurityLogService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RetentionPolicyEngine
{
    public function __construct(
        private readonly RetentionResourceCatalog $catalog,
        private readonly RetentionHandlerRegistry $handlerRegistry,
        private readonly RetentionExceptionChecker $exceptionChecker,
        private readonly AuditLogger $auditLogger,
        private readonly SecurityLogService $securityLogService,
    ) {}

    public function preview(
        ?RetentionPolicy $policy = null,
        ?string $resourceType = null,
        ?CarbonInterface $at = null,
        int $batchSize = 0,
    ): RetentionRun {
        return $this->run(
            mode: RetentionRunMode::Preview,
            policy: $policy,
            resourceType: $resourceType,
            at: $at,
            batchSize: $batchSize,
            maxItems: null,
            resumeRun: null,
            dryRun: true,
            actor: null,
        );
    }

    public function execute(
        ?RetentionPolicy $policy = null,
        ?string $resourceType = null,
        int $batchSize = 0,
        ?int $maxItems = null,
        ?RetentionRun $resumeRun = null,
        bool $dryRun = false,
        ?User $actor = null,
        ?CarbonInterface $at = null,
    ): RetentionRun {
        return $this->run(
            mode: $dryRun ? RetentionRunMode::Preview : RetentionRunMode::Execute,
            policy: $policy,
            resourceType: $resourceType,
            at: $at,
            batchSize: $batchSize,
            maxItems: $maxItems,
            resumeRun: $resumeRun,
            dryRun: $dryRun,
            actor: $actor,
        );
    }

    public function canActivate(RetentionPolicy $policy): bool
    {
        if ($policy->status !== RetentionPolicyStatus::Draft) {
            return false;
        }

        if (! $this->hasFreshPreview($policy)) {
            return false;
        }

        $definition = $this->catalog->get($policy->resource_type);

        if (! $definition->supportsTrigger($policy->trigger_type) || ! $definition->supportsAction($policy->action)) {
            return false;
        }

        if ($policy->action !== RetentionPolicyAction::RetainRestricted && ! $policy->hasApprovedRetentionPeriod()) {
            return false;
        }

        return filled($policy->reason);
    }

    public function hasFreshPreview(RetentionPolicy $policy): bool
    {
        if ($policy->last_previewed_at === null || $policy->last_preview_count === null) {
            return false;
        }

        $freshnessHours = (int) config('privacy_retention.preview_freshness_hours', 24);
        $cutoff = now()->subHours($freshnessHours);

        if ($policy->last_previewed_at->lt($cutoff)) {
            return false;
        }

        $latestPreview = RetentionRun::query()
            ->where('retention_policy_id', $policy->id)
            ->where('mode', RetentionRunMode::Preview)
            ->where('status', RetentionRunStatus::Completed)
            ->latest('id')
            ->first();

        if ($latestPreview === null) {
            return false;
        }

        return $latestPreview->failed_count === 0;
    }

    public function activate(RetentionPolicy $policy, User $actor): RetentionPolicy
    {
        if (! $actor->can('retention_policies.activate')) {
            $this->securityLogService->record(
                'retention.execution_denied',
                SecurityLogResult::Denied,
                SecurityLogSeverity::Warning,
                $actor,
            );

            throw new RuntimeException('Activation denied.');
        }

        if (! $this->canActivate($policy)) {
            throw new InvalidArgumentException('Policy cannot be activated.');
        }

        RetentionPolicy::query()
            ->where('resource_type', $policy->resource_type)
            ->where('status', RetentionPolicyStatus::Active)
            ->update(['status' => RetentionPolicyStatus::Superseded]);

        $policy->forceFill([
            'status' => RetentionPolicyStatus::Active,
            'activated_at' => now(),
            'activated_by' => $actor->id,
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_policy.activated',
            AuditLogResult::Success,
            metadata: [
                'policy_uuid' => $policy->uuid,
                'resource_type' => $policy->resource_type,
                'expected_count' => $policy->last_preview_count,
            ],
        );

        return $policy->fresh();
    }

    public function deactivate(RetentionPolicy $policy, User $actor): RetentionPolicy
    {
        if (! $actor->can('retention_policies.activate')) {
            throw new RuntimeException('Deactivation denied.');
        }

        $policy->forceFill([
            'status' => RetentionPolicyStatus::Inactive,
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_policy.deactivated',
            AuditLogResult::Success,
            metadata: [
                'policy_uuid' => $policy->uuid,
                'resource_type' => $policy->resource_type,
            ],
        );

        return $policy->fresh();
    }

    private function run(
        RetentionRunMode $mode,
        ?RetentionPolicy $policy,
        ?string $resourceType,
        ?CarbonInterface $at,
        int $batchSize,
        ?int $maxItems,
        ?RetentionRun $resumeRun,
        bool $dryRun,
        ?User $actor,
    ): RetentionRun {
        $batchSize = $batchSize > 0 ? $batchSize : (int) config('privacy_retention.default_batch_size', 100);
        $at ??= now();

        $policies = $this->resolvePolicies($policy, $resourceType, $mode, $dryRun);

        if ($policies->isEmpty()) {
            return $this->createSkippedRun($mode, $resourceType, $at, $actor);
        }

        $lastRun = null;

        foreach ($policies as $activePolicy) {
            $lock = $this->acquirePolicyLock($activePolicy, $mode);
            if ($lock === null) {
                $this->securityLogService->record(
                    'retention.concurrent_run_blocked',
                    SecurityLogResult::Denied,
                    SecurityLogSeverity::Warning,
                    $actor,
                );

                continue;
            }

            try {
                $lastRun = $this->processPolicy(
                    $activePolicy,
                    $mode,
                    $at,
                    $batchSize,
                    $maxItems,
                    $resumeRun,
                    $dryRun,
                    $actor,
                );
            } finally {
                $lock->release();
            }
        }

        return $lastRun ?? $this->createSkippedRun($mode, $resourceType, $at, $actor);
    }

    /**
     * @return \Illuminate\Support\Collection<int, RetentionPolicy>
     */
    private function resolvePolicies(
        ?RetentionPolicy $policy,
        ?string $resourceType,
        RetentionRunMode $mode,
        bool $dryRun,
    ): \Illuminate\Support\Collection {
        if ($policy !== null) {
            if ($mode === RetentionRunMode::Execute && ! $dryRun && ! $this->isPolicyExecutable($policy)) {
                throw new InvalidArgumentException('Policy is not executable.');
            }

            return collect([$policy]);
        }

        $query = RetentionPolicy::query()->where('status', RetentionPolicyStatus::Active);

        if ($resourceType !== null) {
            $query->where('resource_type', $resourceType);
        }

        return $query->get()->filter(function (RetentionPolicy $policy) use ($mode, $dryRun): bool {
            if ($mode === RetentionRunMode::Preview) {
                return $this->catalog->get($policy->resource_type)->schedulable;
            }

            return $dryRun || $this->isPolicyExecutable($policy);
        })->values();
    }

    private function isPolicyExecutable(RetentionPolicy $policy): bool
    {
        if (! $policy->isActive()) {
            return false;
        }

        if (! $policy->isEffectiveAt()) {
            return false;
        }

        if ($policy->action === RetentionPolicyAction::RetainRestricted) {
            return false;
        }

        if (! $policy->hasApprovedRetentionPeriod()) {
            return false;
        }

        $definition = $this->catalog->get($policy->resource_type);

        return $definition->schedulable
            && $definition->supportsTrigger($policy->trigger_type)
            && $definition->supportsAction($policy->action);
    }

    private function processPolicy(
        RetentionPolicy $policy,
        RetentionRunMode $mode,
        CarbonInterface $at,
        int $batchSize,
        ?int $maxItems,
        ?RetentionRun $resumeRun,
        bool $dryRun,
        ?User $actor,
    ): RetentionRun {
        $handler = $this->handlerRegistry->forResource($policy->resource_type);
        $cutoff = $this->calculateCutoff($policy, $at);

        $run = $resumeRun ?? RetentionRun::query()->create([
            'retention_policy_id' => $policy->id,
            'resource_type' => $policy->resource_type,
            'mode' => $mode,
            'status' => RetentionRunStatus::Running,
            'started_by' => $actor?->id,
            'started_at' => now(),
            'cutoff_at' => $cutoff,
            'request_id' => request()?->attributes->get('request_id'),
        ]);

        if ($resumeRun === null) {
            $this->auditLogger->recordOrFail(
                $actor,
                'retention_run.started',
                AuditLogResult::Success,
                metadata: [
                    'run_uuid' => $run->uuid,
                    'policy_uuid' => $policy->uuid,
                    'resource_type' => $policy->resource_type,
                    'mode' => $mode->value,
                ],
            );
        } else {
            $this->auditLogger->recordOrFail(
                $actor,
                'retention_run.resumed',
                AuditLogResult::Success,
                metadata: [
                    'run_uuid' => $run->uuid,
                    'policy_uuid' => $policy->uuid,
                ],
            );
        }

        $query = $handler->eligibleQuery($policy, $cutoff);
        $eligibleCount = $this->countEligible($query);
        $excludedCount = 0;
        $processed = (int) ($run->processed_count ?? 0);
        $succeeded = (int) ($run->succeeded_count ?? 0);
        $skipped = (int) ($run->skipped_count ?? 0);
        $failed = (int) ($run->failed_count ?? 0);
        $remaining = $maxItems;

        $this->chunkEligible($query, $batchSize, function (object $record) use (
            $handler,
            $policy,
            $run,
            $dryRun,
            &$processed,
            &$succeeded,
            &$skipped,
            &$failed,
            &$excludedCount,
            &$remaining,
        ): bool {
            if ($remaining !== null && $remaining <= 0) {
                return false;
            }

            if ($handler->isExcludedByException($record, $policy)) {
                $excludedCount++;
                if ($handler->tracksRunItems()) {
                    $this->recordItem($run, $handler, $record, $policy, RetentionRunItemStatus::Skipped, 'retention_exception', $dryRun);
                }

                return true;
            }

            $item = $handler->tracksRunItems()
                ? $this->findOrCreateItem($run, $handler, $record, $policy)
                : null;

            if ($item !== null && $item->status === RetentionRunItemStatus::Succeeded) {
                return true;
            }

            if ($item !== null) {
                $item->forceFill([
                    'status' => RetentionRunItemStatus::Running,
                    'attempts' => $item->attempts + 1,
                    'started_at' => now(),
                ])->save();
            }

            $result = $handler->process($policy, $run, $item, $record, $dryRun);
            $processed++;

            if ($result->status === RetentionRunItemStatus::Succeeded) {
                $succeeded++;
            } elseif ($result->status === RetentionRunItemStatus::Skipped) {
                $skipped++;
            } else {
                $failed++;
                $this->auditLogger->record(
                    null,
                    'retention_item.failed',
                    AuditLogResult::Failure,
                    metadata: [
                        'run_uuid' => $run->uuid,
                        'resource_type' => $policy->resource_type,
                        'failure_code' => $result->failureCode,
                    ],
                );
            }

            if ($item !== null) {
                $item->forceFill([
                    'status' => $result->status,
                    'failure_code' => $result->failureCode,
                    'completed_at' => now(),
                ])->save();
            }

            if ($remaining !== null) {
                $remaining--;
            }

            return true;
        });

        $status = match (true) {
            $failed > 0 && $succeeded > 0 => RetentionRunStatus::CompletedWithFailures,
            $failed > 0 => RetentionRunStatus::Failed,
            default => RetentionRunStatus::Completed,
        };

        $run->forceFill([
            'eligible_count' => $eligibleCount,
            'excluded_count' => $excludedCount,
            'processed_count' => $processed,
            'succeeded_count' => $succeeded,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'status' => $status,
            'completed_at' => now(),
            'summary' => [
                'dry_run' => $dryRun,
                'resource_type' => $policy->resource_type,
            ],
        ])->save();

        if ($mode === RetentionRunMode::Preview) {
            $policy->forceFill([
                'last_previewed_at' => now(),
                'last_preview_count' => max(0, $eligibleCount - $excludedCount),
            ])->save();

            $this->auditLogger->recordOrFail(
                $actor,
                'retention_policy.previewed',
                AuditLogResult::Success,
                metadata: [
                    'policy_uuid' => $policy->uuid,
                    'run_uuid' => $run->uuid,
                    'eligible_count' => $eligibleCount,
                    'excluded_count' => $excludedCount,
                ],
            );
        } else {
            $action = $status === RetentionRunStatus::CompletedWithFailures
                ? 'retention_run.completed_with_failures'
                : ($status === RetentionRunStatus::Failed ? 'retention_run.failed' : 'retention_run.completed');

            $this->auditLogger->recordOrFail(
                $actor,
                $action,
                $status === RetentionRunStatus::Failed ? AuditLogResult::Failure : AuditLogResult::Success,
                metadata: [
                    'run_uuid' => $run->uuid,
                    'policy_uuid' => $policy->uuid,
                    'processed_count' => $processed,
                    'failed_count' => $failed,
                ],
            );
        }

        return $run->fresh();
    }

    private function calculateCutoff(RetentionPolicy $policy, CarbonInterface $at): CarbonInterface
    {
        $days = (int) $policy->retention_period_days + (int) $policy->grace_period_days;

        return Carbon::parse($at)->subDays($days);
    }

    private function countEligible(EloquentBuilder|QueryBuilder $query): int
    {
        if ($query instanceof EloquentBuilder) {
            return (int) $query->toBase()->getCountForPagination();
        }

        return (int) $query->count();
    }

    private function chunkEligible(EloquentBuilder|QueryBuilder $query, int $batchSize, callable $callback): void
    {
        if ($query instanceof EloquentBuilder) {
            $query->orderBy('id')->chunkById($batchSize, function ($records) use ($callback): bool {
                foreach ($records as $record) {
                    if ($callback($record) === false) {
                        return false;
                    }
                }

                return true;
            });

            return;
        }

        $query->chunk($batchSize, function ($records) use ($callback): bool {
            foreach ($records as $record) {
                if ($callback($record) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    private function findOrCreateItem(
        RetentionRun $run,
        RetentionResourceHandler $handler,
        object $record,
        RetentionPolicy $policy,
    ): RetentionRunItem {
        return RetentionRunItem::query()->firstOrCreate(
            [
                'retention_run_id' => $run->id,
                'resource_type' => $policy->resource_type,
                'resource_identifier' => $handler->opaqueIdentifier($record),
            ],
            [
                'source_id' => $handler->sourceId($record),
                'action' => $policy->action,
                'status' => RetentionRunItemStatus::Pending,
            ],
        );
    }

    private function recordItem(
        RetentionRun $run,
        RetentionResourceHandler $handler,
        object $record,
        RetentionPolicy $policy,
        RetentionRunItemStatus $status,
        ?string $failureCode,
        bool $dryRun,
    ): void {
        RetentionRunItem::query()->updateOrCreate(
            [
                'retention_run_id' => $run->id,
                'resource_type' => $policy->resource_type,
                'resource_identifier' => $handler->opaqueIdentifier($record),
            ],
            [
                'source_id' => $handler->sourceId($record),
                'action' => $policy->action,
                'status' => $status,
                'failure_code' => $failureCode,
                'completed_at' => now(),
            ],
        );
    }

    private function acquirePolicyLock(RetentionPolicy $policy, RetentionRunMode $mode): ?Lock
    {
        $lock = Cache::lock(
            'privacy:retention:'.$policy->uuid.':'.$mode->value,
            (int) config('privacy_retention.lock_ttl_seconds', 3600),
        );

        return $lock->get() ? $lock : null;
    }

    private function createSkippedRun(
        RetentionRunMode $mode,
        ?string $resourceType,
        CarbonInterface $at,
        ?User $actor,
    ): RetentionRun {
        return RetentionRun::query()->create([
            'resource_type' => $resourceType,
            'mode' => $mode,
            'status' => RetentionRunStatus::Completed,
            'started_by' => $actor?->id,
            'started_at' => now(),
            'completed_at' => now(),
            'cutoff_at' => $at,
            'summary' => ['skipped' => 'no_executable_policies'],
        ]);
    }
}
