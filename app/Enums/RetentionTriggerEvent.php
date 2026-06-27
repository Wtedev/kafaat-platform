<?php

namespace App\Enums;

enum RetentionTriggerEvent: string
{
    case CreatedAt = 'created_at';
    case UpdatedAt = 'updated_at';
    case LastUpdatedAt = 'last_updated_at';
    case ExpiredAt = 'expired_at';
    case CompletedAt = 'completed_at';
    case RequestCompletedAt = 'request_completed_at';
    case AccountAnonymizedAt = 'account_anonymized_at';
    case AccountDeletedAt = 'account_deleted_at';
    case ConsentWithdrawnAt = 'consent_withdrawn_at';
    case LastActivityAt = 'last_activity_at';

    public function label(): string
    {
        return match ($this) {
            self::CreatedAt => 'تاريخ الإنشاء',
            self::UpdatedAt, self::LastUpdatedAt => 'تاريخ التحديث',
            self::ExpiredAt => 'تاريخ الانتهاء',
            self::CompletedAt, self::RequestCompletedAt => 'تاريخ الإكمال',
            self::AccountAnonymizedAt, self::AccountDeletedAt => 'تاريخ تعمية الحساب',
            self::ConsentWithdrawnAt => 'تاريخ سحب الموافقة',
            self::LastActivityAt => 'آخر نشاط',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::LastUpdatedAt => 'updated_at',
            self::RequestCompletedAt => 'completed_at',
            self::AccountDeletedAt => 'account_anonymized_at',
            default => $this->value,
        };
    }
}
