<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\LearningPath;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\TrainingProgram;

class PathAttendanceService
{
    public function countExpectedTrainingDays(LearningPath $path): int
    {
        $path->loadMissing('programs');

        $dates = [];

        foreach ($path->programs as $program) {
            foreach ($this->expectedDatesForProgram($program) as $date) {
                $dates[$date] = true;
            }
        }

        return count($dates);
    }

    /**
     * @return list<string>
     */
    public function expectedDatesForProgram(TrainingProgram $program): array
    {
        if (
            $program->start_date === null
            || $program->end_date === null
            || empty($program->weekdays)
        ) {
            return [];
        }

        $weekdays = array_map('intval', $program->weekdays);
        $dates = [];
        $current = $program->start_date->copy();
        $end = $program->end_date->copy();

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $weekdays, strict: true)) {
                $dates[] = $current->toDateString();
            }

            $current->addDay();
        }

        return $dates;
    }

    public function calculatePercentage(PathRegistration $registration): ?float
    {
        $registration->loadMissing('learningPath.programs');
        $expectedDays = $this->countExpectedTrainingDays($registration->learningPath);

        if ($expectedDays === 0) {
            return null;
        }

        $present = $registration->attendanceRecords()
            ->where('status', AttendanceStatus::Present->value)
            ->count();

        return round($present / $expectedDays * 100, 2);
    }

    public function markManualDay(PathRegistration $registration, string $date, AttendanceStatus $status, ?string $notes = null): void
    {
        PathAttendance::updateOrCreate(
            [
                'path_registration_id' => $registration->id,
                'attendance_date' => $date,
            ],
            [
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    public function generateSessions(PathRegistration $registration): int
    {
        $registration->loadMissing('learningPath.programs');
        $created = 0;

        foreach ($registration->learningPath->programs as $program) {
            foreach ($this->expectedDatesForProgram($program) as $date) {
                $record = PathAttendance::firstOrCreate(
                    [
                        'path_registration_id' => $registration->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'status' => AttendanceStatus::Absent,
                    ],
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    public function generateSessionsForAllRegistrations(LearningPath $path): int
    {
        $total = 0;

        $path->registrations()
            ->whereIn('status', [
                \App\Enums\RegistrationStatus::Approved->value,
                \App\Enums\RegistrationStatus::Completed->value,
            ])
            ->each(function (PathRegistration $registration) use (&$total): void {
                $total += $this->generateSessions($registration);
            });

        return $total;
    }
}
