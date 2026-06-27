<?php

namespace App\Services\Privacy\Retention;

use App\Enums\RetentionExceptionScope;
use App\Models\RetentionException;
use App\Models\RetentionPolicy;
use Illuminate\Support\Collection;

final class RetentionExceptionChecker
{
    /**
     * @return Collection<int, RetentionException>
     */
    public function activeExceptionsForResourceType(string $resourceType): Collection
    {
        return RetentionException::query()
            ->where('resource_type', $resourceType)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->get()
            ->filter(fn (RetentionException $exception): bool => $exception->isActiveAt());
    }

    public function isRecordExcluded(
        string $resourceType,
        ?int $resourceId,
        ?int $userId,
    ): bool {
        $exceptions = $this->activeExceptionsForResourceType($resourceType);

        foreach ($exceptions as $exception) {
            if ($exception->scope === RetentionExceptionScope::ResourceTypeAll) {
                return true;
            }

            if ($exception->scope === RetentionExceptionScope::UserAllResources
                && $userId !== null
                && $exception->user_id === $userId) {
                return true;
            }

            if ($exception->scope === RetentionExceptionScope::SingleResource
                && $resourceId !== null
                && $exception->resource_id === $resourceId) {
                return true;
            }
        }

        return false;
    }

    public function countExcludedForPolicy(RetentionPolicy $policy, int $eligibleBeforeExclusions): int
    {
        return 0;
    }
}
