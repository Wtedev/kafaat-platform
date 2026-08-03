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
     * Union of explicit prep days requiring attendance across path programs.
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
     * Due prep days for % denominator: requires attendance and date <= today (Asia/Riyadh).
     *
     * @return list<string>
     */
    public function dueAttendanceDateStrings(LearningPath $path, ?Carbon $asOf = null): array
    {
        $today = ($asOf ?? Carbon::today(config('app.timezone')))->toDateString();

        return array_values(array_filter(
            $this->expectedAttendanceDateStrings($path),
            static fn (string $date): bool => $date <= $today,
        ));
    }

    /**
     * Explicit prep days that require attendance (not weekdays expansion).
     *
     * @return list<string>
     */
    public function expectedDatesForProgram(TrainingProgram $program): array
    {
        return app(ProgramAttendanceService::class)->attendancePrepDateStrings($program);
    }

    /**
     * Attendance % = (present + late) / due prep days up to today.
     * Returns null when no due prep days yet (UI shows «—»).
     */
    public function calculatePercentage(PathRegistration $registration, ?Carbon $asOf = null): ?float
    {
        $registration->loadMissing('learningPath.programs');
        $dueDays = $this->dueAttendanceDateStrings($registration->learningPath, $asOf);

        if ($dueDays === []) {
            return null;
        }

        $attended = $registration->attendanceRecords()
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->get()
            ->filter(fn (PathAttendance $row): bool => in_array(
                $row->attendance_date->toDateString(),
                $dueDays,
                true,
            ))
            ->count();

        return round($attended / count($dueDays) * 100, 2);
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
