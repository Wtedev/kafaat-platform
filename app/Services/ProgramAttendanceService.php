<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;

class ProgramAttendanceService
{
    /**
     * Arabic day names keyed by Carbon dayOfWeek (0 = Sunday … 6 = Saturday).
     */
    private const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    /**
     * Generate expected training sessions for a single registration.
     *
     * Iterates over every date in the program's [start_date, end_date] range
     * and creates a ProgramAttendance row (status = absent) for each date
     * whose day-of-week matches one of the program's configured weekdays.
     *
     * Existing records are NOT overwritten (idempotent).
     *
     * @return int  Number of NEW rows inserted (0 means nothing was created,
     *              either because weekdays/dates are not configured or all
     *              sessions already existed).
     */
    public function generateSessions(ProgramRegistration $registration): int
    {
        $registration->loadMissing('trainingProgram');
        $program = $registration->trainingProgram;

        if (
            $program->start_date === null
            || $program->end_date === null
            || empty($program->weekdays)
        ) {
            return 0;
        }

        // Weekdays are stored as strings by Filament CheckboxList; cast to int
        // so they match Carbon's dayOfWeek integer values.
        $weekdays = array_map('intval', $program->weekdays);

        $current = $program->start_date->copy();
        $end     = $program->end_date->copy();
        $created = 0;

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $weekdays, strict: true)) {
                $record = ProgramAttendance::firstOrCreate(
                    [
                        'program_registration_id' => $registration->id,
                        'training_date'           => $current->toDateString(),
                    ],
                    [
                        'status' => AttendanceStatus::Absent,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                }
            }

            $current->addDay();
        }

        return $created;
    }

    /**
     * Generate sessions for ALL approved and completed registrations of a program.
     *
     * @return int  Total new rows inserted across all registrations.
     */
    public function generateSessionsForAllRegistrations(TrainingProgram $program): int
    {
        $total = 0;

        $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->each(function (ProgramRegistration $registration) use (&$total): void {
                $total += $this->generateSessions($registration);
            });

        return $total;
    }

    /**
     * Calculate the attendance percentage for a registration from its daily records.
     *
     * Returns null when no attendance records exist (fall back to stored value).
     * Excused absences count as absent for percentage purposes.
     */
    public function calculatePercentage(ProgramRegistration $registration): ?float
    {
        $total = $registration->attendanceRecords()->count();

        if ($total === 0) {
            return null;
        }

        $present = $registration->attendanceRecords()
            ->where('status', AttendanceStatus::Present->value)
            ->count();

        return round($present / $total * 100, 2);
    }

    /**
     * Return a summary array: ['total' => int, 'present' => int, 'absent' => int, 'excused' => int].
     */
    public function getSummary(ProgramRegistration $registration): array
    {
        $records = $registration->attendanceRecords()
            ->select('status')
            ->get()
            ->groupBy('status');

        return [
            'total'   => $records->sum(fn ($g) => $g->count()),
            'present' => $records->get(AttendanceStatus::Present->value)?->count() ?? 0,
            'absent'  => $records->get(AttendanceStatus::Absent->value)?->count() ?? 0,
            'excused' => $records->get(AttendanceStatus::Excused->value)?->count() ?? 0,
        ];
    }

    /**
     * Translate a dayOfWeek integer (0–6) to an Arabic day name.
     */
    public function dayName(int $dayOfWeek): string
    {
        return self::DAY_NAMES[$dayOfWeek] ?? (string) $dayOfWeek;
    }
}
