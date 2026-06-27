<?php

namespace App\Data\Privacy\Export;

use App\Models\PathAttendance;
use App\Models\ProgramAttendance;
use App\Models\User;

final readonly class AttendanceExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        $program = ProgramAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->with('registration.trainingProgram:id,title')
            ->orderBy('training_date')
            ->get()
            ->map(fn (ProgramAttendance $record): array => [
                'context' => 'program',
                'program_title' => $record->registration?->trainingProgram?->title,
                'training_date' => $record->training_date?->toDateString(),
                'status' => $record->status?->value,
            ]);

        $path = PathAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->with('registration.learningPath:id,title')
            ->orderBy('attendance_date')
            ->get()
            ->map(fn (PathAttendance $record): array => [
                'context' => 'path',
                'path_title' => $record->registration?->learningPath?->title,
                'attendance_date' => $record->attendance_date?->toDateString(),
                'status' => $record->status?->value,
            ]);

        return $program->concat($path)->values()->all();
    }
}
