<?php

namespace App\Enums;

enum VolunteerHoursStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
        };
    }
}
