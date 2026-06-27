<?php

namespace App\Enums;

enum PrivacyExportFileStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Ready = 'ready';
    case Expired = 'expired';
    case Deleted = 'deleted';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Generating => 'جاري التوليد',
            self::Ready => 'جاهز للتنزيل',
            self::Expired => 'منتهٍ الصلاحية',
            self::Deleted => 'محذوف',
            self::Failed => 'فشل التوليد',
        };
    }

    public function isDownloadable(): bool
    {
        return $this === self::Ready;
    }
}
