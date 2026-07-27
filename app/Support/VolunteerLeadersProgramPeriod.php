<?php

namespace App\Support;

use App\Models\TrainingProgram;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

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

    public static function sidebarHtml(TrainingProgram $program): ?HtmlString
    {
        if (! self::applies($program) || $program->start_date === null || $program->end_date === null) {
            return null;
        }

        $range = en_digits(
            ar_date($program->start_date, 'd MMM y').' – '.ar_date($program->end_date, 'd MMM y')
        );
        $inPerson = en_digits(self::formatInPersonDatesLabel());

        $html = <<<HTML
<span class="block leading-relaxed">{$range}</span>
<span class="mt-2 block space-y-1.5 text-[13px] leading-relaxed">
  <span class="block">
    <span class="font-semibold text-[#335483]">حضوري:</span>
    <span class="text-gray-800"> {$inPerson}</span>
  </span>
  <span class="block">
    <span class="font-semibold text-[#335483]">عن بعد:</span>
    <span class="text-gray-800"> المتبقي من أيام الفترة</span>
  </span>
</span>
HTML;

        return new HtmlString($html);
    }

    /**
     * Groups consecutive August days: «3–4، 8، 16–18 أغسطس 2026».
     */
    public static function formatInPersonDatesLabel(): string
    {
        $dates = collect(self::IN_PERSON_DATES)
            ->map(fn (string $d): Carbon => Carbon::parse($d)->startOfDay())
            ->sortBy(fn (Carbon $d): int => $d->timestamp)
            ->values();

        if ($dates->isEmpty()) {
            return '';
        }

        $groups = [];
        $groupStart = $dates[0];
        $groupEnd = $dates[0];

        for ($i = 1; $i < $dates->count(); $i++) {
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

        $monthYear = ar_date($dates[0], 'MMM y');

        return implode('، ', $groups).' '.$monthYear;
    }

    private static function formatDayGroup(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return (string) $start->day;
        }

        return $start->day.'–'.$end->day;
    }
}
