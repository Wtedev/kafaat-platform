<?php

namespace App\Data\Privacy\Export;

use App\Models\CandidatePoolConsentEvent;
use App\Models\User;

final readonly class CandidateConsentExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return CandidatePoolConsentEvent::query()
            ->where('user_id', $user->id)
            ->with('consentVersion:id,version')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (CandidatePoolConsentEvent $event): array => [
                'event_type' => $event->event_type?->value,
                'consent_version' => $event->consentVersion?->version,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'source' => $event->source?->value,
            ])
            ->values()
            ->all();
    }
}
