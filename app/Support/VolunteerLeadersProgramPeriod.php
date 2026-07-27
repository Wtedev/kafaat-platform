<?php

namespace App\Support;

use App\Models\TrainingProgram;
use Carbon\Carbon;

/**
 * Public sidebar period breakdown for «قادة التطوع».
 */
final class VolunteerLeadersProgramPeriod
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    /**
     * In-person calendar days within the program window (Y-m-d).
     *
     * @var list<string>
     */
    public const IN_PERSON_DATES = [
        '2026-08-03',
        '2026-08-04',
        '2026-08-08',
        '2026-08-16',
        '2026-08-17',
        '2026-08-18',
    ];

    public static function applies(?TrainingProgram $program): bool
    {
        if ($program === null) {
            return false;
        }

        return str_contains((string) $program->title, self::TITLE_NEEDLE);
    }

    /**
     * Display label for in-person days: «3–8 أغسطس، 16–18 أغسطس».
     */
    public static function inPersonDaysLabel(): string
    {
        return en_digits('3–8 أغسطس، 16–18 أغسطس');
    }

    public static function remoteDaysLabel(): string
    {
        return 'المتبقي من أيام الفترة';
    }

    /**
     * Groups consecutive August days: «3–4، 8، 16–18 أغسطس 2026».
     */
    public static function formatInPersonDatesLabel(): string
    {
        $groups = self::inPersonDayGroups();
        $dates = self::sortedInPersonDates();

        if ($groups === [] || $dates === []) {
            return '';
        }

        $monthYear = ar_date($dates[0], 'MMM y');

        return implode('، ', $groups).' '.$monthYear;
    }

    /**
     * @return list<string> e.g. ['3–4', '8', '16–18']
     */
    public static function inPersonDayGroups(): array
    {
        $dates = self::sortedInPersonDates();

        if ($dates === []) {
            return [];
        }

        $groups = [];
        $groupStart = $dates[0];
        $groupEnd = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $current = $dates[$i];
            if ($groupEnd->copy()->addDay()->equalTo($current)) {
                $groupEnd = $current;

                continue;
            }

            $groups[] = self::formatDayGroup($groupStart, $groupEnd);
            $groupStart = $current;
            $groupEnd = $current;
        }

        $groups[] = self::formatDayGroup($groupStart, $groupEnd);

        return $groups;
    }

    /**
     * @return list<Carbon>
     */
    private static function sortedInPersonDates(): array
    {
        return collect(self::IN_PERSON_DATES)
            ->map(fn (string $d): Carbon => Carbon::parse($d)->startOfDay())
            ->sortBy(fn (Carbon $d): int => $d->timestamp)
            ->values()
            ->all();
    }

    private static function formatDayGroup(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return (string) $start->day;
        }

        return $start->day.'–'.$end->day;
    }
}
