<?php

namespace App\Services\Privacy;

use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\PrivacyPolicyVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrivacyPolicyAcknowledgementService
{
    public function acknowledgementText(): string
    {
        return (string) config('privacy.acknowledgement_checkbox_text');
    }

    public function userNeedsAcknowledgement(User $user): bool
    {
        $active = PrivacyPolicyService::active();

        if ($active === null || ! $active->requires_reacknowledgement) {
            return false;
        }

        return ! $this->hasAcknowledgedVersion($user, $active);
    }

    public function hasAcknowledgedVersion(User $user, PrivacyPolicyVersion $version): bool
    {
        return PrivacyPolicyAcknowledgement::query()
            ->where('user_id', $user->id)
            ->where('privacy_policy_version_id', $version->id)
            ->exists();
    }

    public function acknowledge(
        User $user,
        PrivacyPolicyVersion $version,
        PrivacyPolicyAcknowledgementSource $source,
        ?Request $request = null,
    ): PrivacyPolicyAcknowledgement {
        if (! $version->isActive()) {
            throw new RuntimeException('Acknowledgements can only be recorded for the active policy version.');
        }

        if ($this->hasAcknowledgedVersion($user, $version)) {
            return PrivacyPolicyAcknowledgement::query()
                ->where('user_id', $user->id)
                ->where('privacy_policy_version_id', $version->id)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($user, $version, $source, $request): PrivacyPolicyAcknowledgement {
            return PrivacyPolicyAcknowledgement::query()->create([
                'user_id' => $user->id,
                'privacy_policy_version_id' => $version->id,
                'acknowledgement_text_snapshot' => $this->acknowledgementText(),
                'policy_content_hash' => $version->content_hash,
                'acknowledged_at' => now(),
                'source' => $source,
                'ip_address' => $this->resolveClientIp($request),
                'user_agent' => $request?->userAgent(),
            ]);
        });
    }

    private function resolveClientIp(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $ip = $request->ip();

        return filled($ip) ? $ip : null;
    }
}
