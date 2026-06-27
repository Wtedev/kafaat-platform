<?php

namespace App\Data\Privacy\Export;

use App\Models\User;
use App\Models\VolunteerRegistration;

final readonly class VolunteerRegistrationExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return VolunteerRegistration::query()
            ->where('user_id', $user->id)
            ->with('opportunity:id,title')
            ->orderBy('created_at')
            ->get()
            ->map(fn (VolunteerRegistration $registration): array => [
                'opportunity_title' => $registration->opportunity?->title,
                'status' => $registration->status?->value,
                'approved_at' => $registration->approved_at?->toIso8601String(),
                'registered_at' => $registration->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
