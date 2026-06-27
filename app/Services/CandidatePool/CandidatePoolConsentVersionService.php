<?php

namespace App\Services\CandidatePool;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\CandidatePoolConsentVersion;
use Illuminate\Support\Facades\Cache;

class CandidatePoolConsentVersionService
{
    public static function activeVersion(): ?CandidatePoolConsentVersion
    {
        return Cache::remember(
            config('candidate_pool.active_consent_cache_key'),
            now()->addHour(),
            fn (): ?CandidatePoolConsentVersion => CandidatePoolConsentVersion::query()
                ->where('status', PrivacyPolicyVersionStatus::Active)
                ->orderByDesc('effective_at')
                ->first(),
        );
    }

    public static function forgetCache(): void
    {
        Cache::forget(config('candidate_pool.active_consent_cache_key'));
    }
}
