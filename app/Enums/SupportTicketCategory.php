<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case General = 'general';
    case Account = 'account';
    case Registration = 'registration';
    case Technical = 'technical';
    case Certificate = 'certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'عام',
            self::Account => 'الحساب',
            self::Registration => 'التسجيل',
            self::Technical => 'مشكلة تقنية',
            self::Certificate => 'الشهادات',
            self::Other => 'أخرى',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
