<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Data\Privacy\Retention\RetentionActionResult;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class ExpiredPasswordResetRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'password_reset_tokens';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::CreatedAt];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): EloquentBuilder|QueryBuilder
    {
        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return DB::table('password_reset_tokens')
            ->where('created_at', '<=', $cutoff->copy()->subMinutes($expireMinutes))
            ->orderBy('email');
    }

    public function sourceId(object $record): ?int
    {
        return null;
    }

    public function opaqueIdentifier(object $record): string
    {
        $email = is_object($record) && property_exists($record, 'email') ? (string) $record->email : '';

        return $this->hashOpaque('password_reset', $email);
    }

    public function deleteRecord(object $record, bool $dryRun): RetentionActionResult
    {
        if ($dryRun) {
            return RetentionActionResult::succeeded();
        }

        DB::table('password_reset_tokens')
            ->where('email', $record->email)
            ->delete();

        return RetentionActionResult::succeeded();
    }
}
