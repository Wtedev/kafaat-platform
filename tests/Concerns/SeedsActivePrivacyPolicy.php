<?php

namespace Tests\Concerns;

use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyService;
use Database\Seeders\PrivacyPolicySeeder;

trait SeedsActivePrivacyPolicy
{
    protected function seedActivePrivacyPolicy(array $overrides = []): PrivacyPolicyVersion
    {
        if (PrivacyPolicyVersion::query()->active()->exists()) {
            PrivacyPolicyService::forgetCache();

            $existing = PrivacyPolicyService::active();

            if ($existing !== null && $overrides !== []) {
                $existing->forceFill($overrides)->save();
                PrivacyPolicyService::forgetCache();
            }

            return PrivacyPolicyService::activeOrFail();
        }

        $this->seed(PrivacyPolicySeeder::class);

        $policy = PrivacyPolicyService::activeOrFail();

        if ($overrides !== []) {
            $policy->forceFill($overrides)->save();
            PrivacyPolicyService::forgetCache();
        }

        return PrivacyPolicyService::activeOrFail();
    }
}
