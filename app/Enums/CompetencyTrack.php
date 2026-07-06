<?php

namespace App\Enums;

enum CompetencyTrack: string
{
    case Self = 'self';
    case Professional = 'professional';
    case Community = 'community';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'مسار الكفاءة الذاتية',
            self::Professional => 'مسار الكفاءة المهنية',
            self::Community => 'مسار الكفاءة المجتمعية',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Self => 'الكفاءة الذاتية',
            self::Professional => 'الكفاءة المهنية',
            self::Community => 'الكفاءة المجتمعية',
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
