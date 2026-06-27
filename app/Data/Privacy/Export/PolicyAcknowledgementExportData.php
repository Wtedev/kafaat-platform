<?php

namespace App\Data\Privacy\Export;

use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class PolicyAcknowledgementExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return PrivacyPolicyAcknowledgement::query()
            ->where('user_id', $user->id)
            ->with('privacyPolicyVersion')
            ->orderBy('acknowledged_at')
            ->get()
            ->map(fn (PrivacyPolicyAcknowledgement $ack): array => [
                'policy_version' => $ack->privacyPolicyVersion?->version,
                'acknowledged_at' => $ack->acknowledged_at?->toIso8601String(),
                'source' => $ack->source?->value ?? (string) $ack->source,
            ])
            ->values()
            ->all();
    }
}
