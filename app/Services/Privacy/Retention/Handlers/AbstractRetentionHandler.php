<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\RetentionRunItem;
use App\Services\Privacy\Retention\Contracts\RetentionResourceHandler;
use App\Services\Privacy\Retention\RetentionExceptionChecker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

abstract class AbstractRetentionHandler implements RetentionResourceHandler
{
    public function __construct(
        protected readonly RetentionExceptionChecker $exceptionChecker,
    ) {}

    public function hasFiles(): bool
    {
        return false;
    }

    public function tracksRunItems(): bool
    {
        return true;
    }

    public function opaqueIdentifier(object $record): string
    {
        $id = $this->sourceId($record);

        return hash('sha256', static::class.':'.$id);
    }

    public function isExcludedByException(object $record, RetentionPolicy $policy): bool
    {
        return $this->exceptionChecker->isRecordExcluded(
            $this->resourceType(),
            $this->sourceId($record),
            $this->userId($record),
        );
    }

    protected function userId(object $record): ?int
    {
        if (property_exists($record, 'user_id')) {
            return is_numeric($record->user_id) ? (int) $record->user_id : null;
        }

        return null;
    }

    protected function applyTriggerColumn(Builder $query, RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        $column = $policy->trigger_type->column();

        return $query->where($column, '<=', $cutoff);
    }

    protected function deleteRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        if (method_exists($record, 'delete')) {
            $record->delete();

            return RetentionActionResult::succeeded();
        }

        return RetentionActionResult::failed('delete_unsupported');
    }

    protected function deleteById(string $table, int $id, bool $dryRun): RetentionActionResult
    {
        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->delete();

        return RetentionActionResult::succeeded();
    }

    protected function anonymizeColumns(object $record, array $columns, bool $dryRun): RetentionActionResult
    {
        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        $updates = [];
        foreach ($columns as $column => $value) {
            $updates[$column] = is_callable($value) ? $value($record) : $value;
        }

        if (method_exists($record, 'forceFill')) {
            $record->forceFill($updates)->saveQuietly();
        }

        return RetentionActionResult::succeeded();
    }

    protected function resolveAction(RetentionPolicy $policy): RetentionPolicyAction
    {
        return $policy->action;
    }

    public function process(
        RetentionPolicy $policy,
        RetentionRun $run,
        ?RetentionRunItem $item,
        object $record,
        bool $dryRun,
    ): RetentionActionResult {
        if ($this->isExcludedByException($record, $policy)) {
            return RetentionActionResult::skipped('retention_exception');
        }

        return match ($this->resolveAction($policy)) {
            RetentionPolicyAction::Delete => $this->deleteRecord($record, $dryRun),
            RetentionPolicyAction::Anonymize => $this->anonymizeRecord($record, $dryRun),
            RetentionPolicyAction::RetainRestricted => RetentionActionResult::skipped('retain_restricted'),
        };
    }

    protected function anonymizeRecord(object $record, bool $dryRun): RetentionActionResult
    {
        return RetentionActionResult::failed('anonymize_not_implemented');
    }

    protected function hashOpaque(string $prefix, int|string $id): string
    {
        return hash('sha256', $prefix.':'.$id);
    }
}
