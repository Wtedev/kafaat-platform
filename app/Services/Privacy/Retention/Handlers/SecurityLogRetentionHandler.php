<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Enums\SecurityLogSeverity;
use App\Models\RetentionPolicy;
use App\Models\SecurityLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class SecurityLogRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'security_logs';
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
        $protected = config('privacy_retention.protected_security_events', []);

        return SecurityLog::query()
            ->where('occurred_at', '<=', $cutoff)
            ->where('severity', '!=', SecurityLogSeverity::Critical->value)
            ->whereNotIn('event', $protected);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof SecurityLog ? $record->id : null;
    }

    protected function deleteRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof SecurityLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        DB::table('security_logs')->where('id', $record->id)->delete();

        return RetentionActionResult::succeeded();
    }

    protected function anonymizeRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof SecurityLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        return $this->anonymizeColumns($record, [
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'identifier_hash' => null,
            'metadata' => [],
        ], $dryRun);
    }
}
