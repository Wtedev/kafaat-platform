<?php

namespace App\Data\Privacy\Export;

use App\Models\ProgramRegistration;
use App\Models\User;

final readonly class ProgramRegistrationExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->with('trainingProgram:id,title,slug')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProgramRegistration $registration): array => [
                'program_title' => $registration->trainingProgram?->title,
                'program_slug' => $registration->trainingProgram?->slug,
                'status' => $registration->status?->value,
                'approved_at' => $registration->approved_at?->toIso8601String(),
                'attendance_percentage' => $registration->effectiveAttendancePercentage(),
                'score' => $registration->score !== null ? (float) $registration->score : null,
                'registered_at' => $registration->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
