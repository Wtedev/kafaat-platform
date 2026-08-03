<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Late => 'متأخر',
            self::Absent => 'غائب',
            self::Excused => 'بعذر',
        };
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Excused => 'info',
        };
    }

    /**
     * Statuses that count as attended for percentage.
     *
     * @return list<self>
     */
    public static function attendedCases(): array
    {
        return [self::Present, self::Late];
    }

    /**
     * @return list<string>
     */
    public static function attendedValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::attendedCases(),
        );
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
