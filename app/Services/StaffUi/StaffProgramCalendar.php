<?php

namespace App\Services\StaffUi;

use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;

/**
 * Month grids for the program «about» tab: registration window, program days,
 * and the attendance days (prep days and attendance links, QR or link alike).
 */
final class StaffProgramCalendar
{
    /** Never render more than this many months. */
    private const MAX_MONTHS = 12;

    /**
     * @return array{
     *     months: list<array{key: string, label: string, weeks: list<list<array<string, mixed>|null>>}>,
     *     initial: int,
     *     has_any: bool,
     * }
     */
    public function build(TrainingProgram $program, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $tz = config('app.timezone');

        $attendanceDays = [];
        foreach ($program->prepDays()->pluck('prep_date') as $date) {
            if ($date !== null) {
                $attendanceDays[$date->toDateString()] = true;
            }
        }

        foreach ($program->attendanceLinks()->whereNull('cancelled_at')->pluck('opens_at') as $opensAt) {
            if ($opensAt !== null) {
                $attendanceDays[$opensAt->copy()->timezone($tz)->toDateString()] = true;
            }
        }

        $registrationStart = $program->registration_start?->copy()->startOfDay();
        $registrationEnd = $program->registration_end?->copy()->startOfDay();
        $programStart = $program->start_date?->copy()->startOfDay();
        $programEnd = ($program->end_date ?? $program->start_date)?->copy()->startOfDay();
        $weekdays = is_array($program->weekdays) && $program->weekdays !== []
            ? array_map('intval', $program->weekdays)
            : null;

        $points = array_filter([
            $registrationStart,
            $registrationEnd,
            $programStart,
            $programEnd,
            ...array_map(static fn (string $d): Carbon => Carbon::parse($d), array_keys($attendanceDays)),
        ]);

        if ($points === []) {
            return ['months' => [], 'initial' => 0, 'has_any' => false];
        }

        $first = collect($points)->min()->copy()->startOfMonth();
        $last = collect($points)->max()->copy()->startOfMonth();
        if ($first->diffInMonths($last) >= self::MAX_MONTHS) {
            $last = $first->copy()->addMonths(self::MAX_MONTHS - 1);
        }

        $months = [];
        $initial = $today->gt($last->copy()->endOfMonth()) ? -1 : 0;
        for ($month = $first->copy(); $month->lte($last); $month->addMonth()) {
            if ($month->isSameMonth($today)) {
                $initial = count($months);
            }

            $weeks = [];
            $week = array_fill(0, $month->dayOfWeek, null);
            $cursor = $month->copy();
            while ($cursor->isSameMonth($month)) {
                $key = $cursor->toDateString();
                $inProgram = $programStart !== null
                    && $cursor->betweenIncluded($programStart, $programEnd)
                    && ($weekdays === null || in_array($cursor->dayOfWeek, $weekdays, true));

                $week[] = [
                    'day' => $cursor->day,
                    'date' => $key,
                    'today' => $cursor->isSameDay($today),
                    'registration' => $registrationStart !== null
                        && $cursor->gte($registrationStart)
                        && ($registrationEnd === null || $cursor->lte($registrationEnd)),
                    'program' => $inProgram,
                    'attendance' => isset($attendanceDays[$key]),
                ];

                if (count($week) === 7) {
                    $weeks[] = $week;
                    $week = [];
                }
                $cursor->addDay();
            }
            if ($week !== []) {
                $weeks[] = array_pad($week, 7, null);
            }

            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => ar_date($month, 'MMMM y'),
                'weeks' => $weeks,
            ];
        }

        if ($initial < 0) {
            $initial = count($months) - 1;
        }

        return ['months' => $months, 'initial' => $initial, 'has_any' => true];
    }
}
