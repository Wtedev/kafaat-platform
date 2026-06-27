<?php

namespace App\Enums;

enum CandidatePoolPreferenceStatus: string
{
    case Undecided = 'undecided';
    case Granted = 'granted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Undecided => 'لم يحدد',
            self::Granted => 'منضم',
            self::Declined => 'رفض',
            self::Withdrawn => 'سحب الموافقة',
        };
    }
}
