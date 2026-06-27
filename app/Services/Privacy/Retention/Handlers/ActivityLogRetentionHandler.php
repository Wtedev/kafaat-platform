<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use App\Models\UserActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ActivityLogRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'user_activity_logs';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize];
    }

    public function supportedTriggers(): array
    {
        return [
            RetentionTriggerEvent::CreatedAt,
            RetentionTriggerEvent::AccountAnonymizedAt,
            RetentionTriggerEvent::AccountDeletedAt,
        ];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        if (in_array($policy->trigger_type, [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt], true)) {
            return UserActivityLog::query()
                ->whereHas('user', fn (Builder $query) => $query->whereNotNull('account_anonymized_at')
                    ->where('account_anonymized_at', '<=', $cutoff));
        }

        return $this->applyTriggerColumn(UserActivityLog::query(), $policy, $cutoff)
            ->where('occurred_at', '<=', $cutoff);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof UserActivityLog ? $record->id : null;
    }

    protected function anonymizeRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof UserActivityLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        return $this->anonymizeColumns($record, [
            'user_id' => null,
            'title' => 'نشاط محذوف',
            'detail' => null,
        ], $dryRun);
    }
}
