<?php

namespace App\Services\Privacy\Retention\Contracts;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\RetentionRunItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

interface RetentionResourceHandler
{
    public function resourceType(): string;

    /**
     * @return list<RetentionPolicyAction>
     */
    public function supportedActions(): array;

    /**
     * @return list<RetentionTriggerEvent>
     */
    public function supportedTriggers(): array;

    public function hasFiles(): bool;

    public function tracksRunItems(): bool;

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): EloquentBuilder|QueryBuilder;

    public function opaqueIdentifier(object $record): string;

    public function sourceId(object $record): ?int;

    public function isExcludedByException(object $record, RetentionPolicy $policy): bool;

    public function process(
        RetentionPolicy $policy,
        RetentionRun $run,
        ?RetentionRunItem $item,
        object $record,
        bool $dryRun,
    ): RetentionActionResult;
}
