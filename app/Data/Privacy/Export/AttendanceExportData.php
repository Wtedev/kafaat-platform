<?php

namespace App\Data\Privacy\Export;

use App\Models\ProgramAttendance;
use App\Models\User;

final readonly class AttendanceExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return ProgramAttendance::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->with('registration.trainingProgram:id,title')
            ->orderBy('training_date')
            ->get()
            ->map(fn (ProgramAttendance $record): array => [
                'context' => 'program',
                'program_title' => $record->registration?->trainingProgram?->title,
                'training_date' => $record->training_date?->toDateString(),
                'status' => $record->status?->value,
            ])
            ->values()
            ->all();
    }
}
