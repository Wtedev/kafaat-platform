<?php

namespace App\Services\Privacy;

use App\Models\PrivacyPolicyVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PrivacyPolicyService
{
    public static function active(): ?PrivacyPolicyVersion
    {
        $cacheKey = config('privacy.active_policy_cache_key');

        try {
            return Cache::remember(
                $cacheKey,
                now()->addHour(),
                fn (): ?PrivacyPolicyVersion => self::queryActive(),
            );
        } catch (Throwable $exception) {
            Log::warning('privacy_policy.cache_read_failed', [
                'message' => $exception->getMessage(),
            ]);
            Cache::forget($cacheKey);

            return self::queryActive();
        }
    }

    private static function queryActive(): ?PrivacyPolicyVersion
    {
        return PrivacyPolicyVersion::query()
            ->active()
            ->orderByDesc('effective_at')
            ->first();
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
