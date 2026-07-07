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
            self::Self => 'مسار الكفاءة الذاتية',
            self::Professional => 'مسار الكفاءة المهنية',
            self::Community => 'مسار الكفاءة المجتمعية',
        };
    }

    /**
     * @return list<self>
     */
    public static function orderedCases(): array
    {
        $order = config('competency_tracks.order', []);

        if ($order === []) {
            return self::cases();
        }

        $ordered = array_values(array_filter(array_map(
            static fn (mixed $value): ?self => self::tryFrom((string) $value),
            $order,
        )));

        return $ordered !== [] ? $ordered : self::cases();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::orderedCases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function shortOptions(): array
    {
        $out = [];
        foreach (self::orderedCases() as $case) {
            $out[$case->value] = $case->shortLabel();
        }

        return $out;
    }
}
