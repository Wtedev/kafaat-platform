<?php

namespace App\Services\Gate;

use App\Enums\AttendanceStatus;
use App\Models\ProgramRegistration;
use App\Models\User;

final class GateAttendancePresentation
{
    public static function displayName(?User $user): string
    {
        return $user?->fullName() ?: ($user?->name ?? '—');
    }

    public static function isPresent(ProgramRegistration $registration): bool
    {
        return $registration->attendanceRecords
            ->contains(fn ($row) => $row->status === AttendanceStatus::Present);
    }

    /**
     * @param  array<string, mixed>|null  $liveSession
     * @return array<string, mixed>
     */
    public static function liveSessionState(?array $liveSession, int|string|null $sessionMinutes): array
    {
        if ($liveSession !== null) {
            return $liveSession;
        }

        return [
            'can_open' => false,
            'active' => false,
            'ended' => false,
            'session_minutes' => $sessionMinutes ?? 5,
            'remaining_seconds' => 0,
            'expires_at_ms' => null,
            'started_at' => null,
            'expires_at' => null,
            'closed_at' => null,
            'present_count' => 0,
            'approved_count' => 0,
            'attendees' => [],
        ];
    }
}
