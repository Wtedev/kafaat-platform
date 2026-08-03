<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\LearningPath;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\TrainingProgram;
use Illuminate\Support\Carbon;

class PathAttendanceService
{
    public function countExpectedTrainingDays(LearningPath $path): int
    {
        return count($this->expectedAttendanceDateStrings($path));
    }

    /**
     * Union of all program prep days across path programs (including future).
     *
     * @return list<string>
     */
    public function expectedAttendanceDateStrings(LearningPath $path): array
    {
        $path->loadMissing('programs');

        $dates = [];

        foreach ($path->programs as $program) {
            foreach ($this->expectedDatesForProgram($program) as $date) {
                $dates[$date] = true;
            }
        }

        $sorted = array_keys($dates);
        sort($sorted);

        return $sorted;
    }

    /**
     * @deprecated Prefer expectedAttendanceDateStrings — kept for older call sites.
     *
     * @return list<string>
     */
    public function dueAttendanceDateStrings(LearningPath $path, ?Carbon $asOf = null): array
    {
        return $this->expectedAttendanceDateStrings($path);
    }

    /**
     * All program prep days for a program (shared source of truth).
     *
     * @return list<string>
     */
    public function expectedDatesForProgram(TrainingProgram $program): array
    {
        return app(ProgramAttendanceService::class)->attendancePrepDateStrings($program);
    }

    /**
     * Path % = (present + late) / all expected prep days × 100.
     * PathAttendance keeps its own status model; denominator aligns with program prep days.
     * Returns null when no expected days (UI shows «—»).
     */
    public function calculatePercentage(PathRegistration $registration, ?Carbon $asOf = null): ?float
    {
        $registration->loadMissing('learningPath.programs');
        $expectedDays = $this->expectedAttendanceDateStrings($registration->learningPath);

        if ($expectedDays === []) {
            return null;
        }

        $attended = $registration->attendanceRecords()
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->get()
            ->filter(fn (PathAttendance $row): bool => in_array(
                $row->attendance_date->toDateString(),
                $expectedDays,
                true,
            ))
            ->count();

        return round($attended / count($expectedDays) * 100, 2);
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

    /**
     * B1 alignment: do not pre-create absent rows.
     */
    public function generateSessions(PathRegistration $registration): int
    {
        return 0;
    }

    public function generateSessionsForAllRegistrations(LearningPath $path): int
    {
        return 0;
    }
}
