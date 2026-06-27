<?php

namespace App\Data\Privacy\Export;

use App\Models\PathRegistration;
use App\Models\User;

final readonly class PathRegistrationExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return PathRegistration::query()
            ->where('user_id', $user->id)
            ->with('learningPath:id,title,slug')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PathRegistration $registration): array => [
                'path_title' => $registration->learningPath?->title,
                'path_slug' => $registration->learningPath?->slug,
                'status' => $registration->status?->value,
                'approved_at' => $registration->approved_at?->toIso8601String(),
                'completed_at' => $registration->completed_at?->toIso8601String(),
                'attendance_percentage' => $registration->effectiveAttendancePercentage(),
                'score' => $registration->score !== null ? (float) $registration->score : null,
                'registered_at' => $registration->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
