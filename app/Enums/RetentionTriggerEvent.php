<?php

namespace App\Enums;

enum RetentionTriggerEvent: string
{
    case CreatedAt = 'created_at';
    case LastUpdatedAt = 'last_updated_at';
    case ExpiredAt = 'expired_at';
    case AccountDeletedAt = 'account_deleted_at';
    case ConsentWithdrawnAt = 'consent_withdrawn_at';
    case RequestCompletedAt = 'request_completed_at';
}
