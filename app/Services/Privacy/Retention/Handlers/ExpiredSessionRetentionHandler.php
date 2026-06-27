<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class ExpiredSessionRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'sessions';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::LastActivityAt];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): EloquentBuilder|QueryBuilder
    {
        return DB::table('sessions')
            ->where('last_activity', '<=', $cutoff->timestamp)
            ->orderBy('id');
    }

    public function sourceId(object $record): ?int
    {
        return null;
    }

    public function opaqueIdentifier(object $record): string
    {
        $id = is_object($record) && property_exists($record, 'id') ? (string) $record->id : '';

        return $this->hashOpaque('session', $id);
    }

    public function deleteRecord(object $record, bool $dryRun): \App\Data\Privacy\Retention\RetentionActionResult
    {
        if ($dryRun) {
            return \App\Data\Privacy\Retention\RetentionActionResult::succeeded();
        }

        DB::table('sessions')->where('id', $record->id)->delete();

        return \App\Data\Privacy\Retention\RetentionActionResult::succeeded();
    }
}
