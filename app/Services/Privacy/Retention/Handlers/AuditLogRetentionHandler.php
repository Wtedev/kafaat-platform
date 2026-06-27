<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\AuditLog;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AuditLogRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'audit_logs';
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
        $protected = config('privacy_retention.protected_audit_actions', []);

        return AuditLog::query()
            ->where('occurred_at', '<=', $cutoff)
            ->whereNotIn('action', $protected);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof AuditLog ? $record->id : null;
    }

    protected function deleteRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof AuditLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        DB::table('audit_logs')->where('id', $record->id)->delete();

        return RetentionActionResult::succeeded();
    }

    protected function anonymizeRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if (! $record instanceof AuditLog) {
            return RetentionActionResult::failed('invalid_record');
        }

        return $this->anonymizeColumns($record, [
            'actor_id' => null,
            'target_user_id' => null,
            'resource_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => [],
            'reason' => null,
        ], $dryRun);
    }
}
