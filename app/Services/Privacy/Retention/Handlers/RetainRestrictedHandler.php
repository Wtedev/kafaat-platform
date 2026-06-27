<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\RetentionRunItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class RetainRestrictedHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'retain_restricted';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::RetainRestricted];
    }

    public function supportedTriggers(): array
    {
        return [];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        throw new \RuntimeException('Retain restricted resources are not schedulable.');
    }

    public function sourceId(object $record): ?int
    {
        return null;
    }

    public function process(
        RetentionPolicy $policy,
        RetentionRun $run,
        ?RetentionRunItem $item,
        object $record,
        bool $dryRun,
    ): RetentionActionResult {
        return RetentionActionResult::skipped('retain_restricted');
    }
}
