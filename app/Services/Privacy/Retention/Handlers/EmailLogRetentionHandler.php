<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\EmailLog;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class EmailLogRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'email_logs';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::CreatedAt];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        return EmailLog::query()->where('sent_at', '<=', $cutoff);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof EmailLog ? $record->id : null;
    }

    protected function anonymizeRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof EmailLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        return $this->anonymizeColumns($record, [
            'recipient_email' => 'redacted@invalid.local',
            'subject' => '[redacted]',
        ], $dryRun);
    }
}
