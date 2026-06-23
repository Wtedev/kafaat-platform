<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Approved => 'مقبول',
            self::Rejected => 'مرفوض',
            self::Cancelled => 'ملغي',
            self::Completed => 'مكتمل',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-[#fef6e6] text-brand ring-1 ring-[#f5dfa8]',
            self::Approved => 'bg-[#e6f5f6] text-brand-secondary ring-1 ring-[#b8e0e2]',
            self::Rejected => 'bg-[#fdeeed] text-brand-danger ring-1 ring-[#f5c4c0]',
            self::Cancelled => 'bg-brand-light text-brand/60 ring-1 ring-brand-border',
            self::Completed => 'bg-brand-light text-brand ring-1 ring-brand-border',
        };
    }

    /** @return array<string, string> */
    public static function badgeClasses(): array
    {
        $classes = [];

        foreach (self::cases() as $status) {
            $classes[$status->value] = $status->badgeClass();
        }

        return $classes;
    }
}
