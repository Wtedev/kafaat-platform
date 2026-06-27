<?php

namespace App\Services\Privacy\Retention;

use App\Enums\RetentionPolicyStatus;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Enums\AuditLogResult;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RetentionPolicyManagementService
{
    public function __construct(
        private readonly RetentionResourceCatalog $catalog,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): RetentionPolicy
    {
        $validated = $this->validateDraftPayload($data);
        $definition = $this->catalog->get($validated['resource_type']);

        $policy = RetentionPolicy::query()->create([
            ...$validated,
            'status' => RetentionPolicyStatus::Draft,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'requires_manual_approval' => $definition->requiresManualApproval,
        ]);

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_policy.created',
            AuditLogResult::Success,
            metadata: [
                'policy_uuid' => $policy->uuid,
                'resource_type' => $policy->resource_type,
            ],
        );

        return $policy;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(RetentionPolicy $policy, array $data, User $actor): RetentionPolicy
    {
        if (! $policy->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Only draft policies can be edited.']);
        }

        $validated = $this->validateDraftPayload($data, $policy);
        $policy->forceFill([
            ...$validated,
            'updated_by' => $actor->id,
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_policy.updated',
            AuditLogResult::Success,
            metadata: [
                'policy_uuid' => $policy->uuid,
                'resource_type' => $policy->resource_type,
            ],
        );

        return $policy->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateDraftPayload(array $data, ?RetentionPolicy $existing = null): array
    {
        $resourceCodes = $this->catalog->codes();

        $validated = Validator::make($data, [
            'resource_type' => ['required', 'string', 'in:'.implode(',', $resourceCodes)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', 'string'],
            'retention_period_days' => ['nullable', 'integer', 'min:0'],
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'action' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10'],
            'effective_at' => ['nullable', 'date'],
        ])->validate();

        $definition = $this->catalog->get($validated['resource_type']);
        $trigger = \App\Enums\RetentionTriggerEvent::tryFrom($validated['trigger_type']);
        $action = \App\Enums\RetentionPolicyAction::tryFrom($validated['action']);

        if ($trigger === null || ! $definition->supportsTrigger($trigger)) {
            throw ValidationException::withMessages(['trigger_type' => 'Unsupported trigger for resource.']);
        }

        if ($action === null || ! $definition->supportsAction($action)) {
            throw ValidationException::withMessages(['action' => 'Unsupported action for resource.']);
        }

        if ($existing === null) {
            $duplicate = RetentionPolicy::query()
                ->where('resource_type', $validated['resource_type'])
                ->where('trigger_type', $trigger)
                ->whereIn('status', [RetentionPolicyStatus::Draft, RetentionPolicyStatus::Active])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['resource_type' => 'An active or draft policy already exists for this resource context.']);
            }
        }

        $validated['trigger_type'] = $trigger;
        $validated['action'] = $action;
        $validated['grace_period_days'] = (int) ($validated['grace_period_days'] ?? 0);

        return $validated;
    }
}
