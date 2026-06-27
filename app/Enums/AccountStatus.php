<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case DeletionPending = 'deletion_pending';
    case DeletionProcessing = 'deletion_processing';
    case Anonymized = 'anonymized';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Inactive => 'معطّل',
            self::DeletionPending => 'حذف قيد المراجعة',
            self::DeletionProcessing => 'حذف قيد التنفيذ',
            self::Anonymized => 'معمى',
        };
    }

    public function allowsLogin(): bool
    {
        return $this === self::Active;
    }
}
