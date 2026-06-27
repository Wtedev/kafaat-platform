<?php

namespace App\Services\Privacy;

use App\Enums\DeletionResourceAction;
use App\Enums\RetentionPolicyAction;
use App\Models\RetentionException;
use App\Models\RetentionPolicy;
use App\Models\User;
use Illuminate\Support\Collection;

final class RetentionPolicyResolver
{
    /**
     * @return Collection<int, RetentionPolicy>
     */
    public function enabledPoliciesForResource(string $resourceType): Collection
    {
        return RetentionPolicy::query()
            ->where('resource_type', $resourceType)
            ->where('status', \App\Enums\RetentionPolicyStatus::Active)
            ->orderByDesc('effective_at')
            ->get();
    }

    public function resolveActionForResource(User $user, string $resourceType): DeletionResourceAction
    {
        $catalogDefault = (string) (config("privacy_deletion.resources.{$resourceType}.default_action") ?? 'retain_restricted');

        $defaultAction = DeletionResourceAction::tryFrom($catalogDefault)
            ?? DeletionResourceAction::RetainRestricted;

        if ($this->hasActiveException($user, $resourceType)) {
            return DeletionResourceAction::SkipDueToRetentionException;
        }

        $policy = $this->enabledPoliciesForResource($resourceType)->first();

        if ($policy === null) {
            return $defaultAction;
        }

        return match ($policy->action) {
            RetentionPolicyAction::Delete => DeletionResourceAction::Delete,
            RetentionPolicyAction::Anonymize => DeletionResourceAction::Anonymize,
            RetentionPolicyAction::RetainRestricted => DeletionResourceAction::RetainRestricted,
        };
    }

    public function hasActiveException(User $user, string $resourceType, ?int $resourceId = null): bool
    {
        $query = RetentionException::query()
            ->where('user_id', $user->id)
            ->where('resource_type', $resourceType)
            ->whereNull('revoked_at');

        if ($resourceId !== null) {
            $query->where('resource_id', $resourceId);
        }

        return $query->get()->contains(fn (RetentionException $exception): bool => $exception->isActiveAt());
    }

    public function awaitingAdministrativePeriod(string $resourceType): bool
    {
        $policy = $this->enabledPoliciesForResource($resourceType)->first();

        if ($policy === null) {
            return in_array($resourceType, [
                'certificates',
                'attendance',
                'program_registrations',
                'path_registrations',
                'volunteer_registrations',
            ], true);
        }

        return $policy->action === RetentionPolicyAction::RetainRestricted
            && $policy->retention_period_days === null;
    }
}
