<?php

namespace App\Services\Privacy;

use App\Models\PrivacyPolicyVersion;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PrivacyPolicyService
{
    public static function active(): ?PrivacyPolicyVersion
    {
        return Cache::remember(
            config('privacy.active_policy_cache_key'),
            now()->addHour(),
            fn (): ?PrivacyPolicyVersion => PrivacyPolicyVersion::query()
                ->active()
                ->orderByDesc('effective_at')
                ->first(),
        );
    }

    public static function activeOrFail(): PrivacyPolicyVersion
    {
        $policy = self::active();

        if ($policy === null) {
            throw new RuntimeException('No active privacy policy version is configured.');
        }

        return $policy;
    }

    public static function forgetCache(): void
    {
        Cache::forget(config('privacy.active_policy_cache_key'));
    }

    public static function findPublishedByVersion(string $version): ?PrivacyPolicyVersion
    {
        return PrivacyPolicyVersion::query()
            ->publishedPublic()
            ->where('version', $version)
            ->first();
    }
}
