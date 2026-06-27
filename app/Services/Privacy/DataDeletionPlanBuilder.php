<?php

namespace App\Services\Privacy;

use App\Data\Privacy\DeletionPlanResourceDecision;
use App\Data\Privacy\DeletionPlanSnapshot;
use App\Enums\DeletionResourceAction;
use App\Models\User;

final class DataDeletionPlanBuilder
{
    public function __construct(
        private readonly RetentionPolicyResolver $retentionPolicyResolver,
    ) {}

    public function buildForUser(User $user): DeletionPlanSnapshot
    {
        $resources = [];
        $catalog = (array) config('privacy_deletion.resources', []);

        foreach ($catalog as $resourceType => $definition) {
            $action = $this->retentionPolicyResolver->resolveActionForResource($user, (string) $resourceType);

            $reason = match ($action) {
                DeletionResourceAction::SkipDueToRetentionException => 'Active retention exception prevents disposal of this resource.',
                DeletionResourceAction::RetainRestricted => 'Retained under restricted retention policy pending administrative disposal period.',
                DeletionResourceAction::Anonymize => 'Personal identifiers are anonymized while preserving relational integrity.',
                DeletionResourceAction::Delete => 'Resource is eligible for deletion under approved policy.',
            };

            $resources[(string) $resourceType] = new DeletionPlanResourceDecision(
                resourceType: (string) $resourceType,
                action: $action,
                reason: $reason,
                awaitingAdministrativePeriod: $this->retentionPolicyResolver->awaitingAdministrativePeriod((string) $resourceType),
            );
        }

        return new DeletionPlanSnapshot(
            resources: $resources,
            generatedAt: now()->toIso8601String(),
        );
    }
}
