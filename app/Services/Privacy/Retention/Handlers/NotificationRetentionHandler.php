<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\InboxNotification;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class NotificationRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'in_app_notifications';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::CreatedAt];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        return $this->applyTriggerColumn(InboxNotification::query(), $policy, $cutoff);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof InboxNotification ? $record->id : null;
    }
}
