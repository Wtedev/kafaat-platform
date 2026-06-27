<?php

namespace App\Data\Privacy;

use App\Enums\DeletionResourceAction;
use InvalidArgumentException;

final readonly class DeletionPlanResourceDecision
{
    public function __construct(
        public string $resourceType,
        public DeletionResourceAction $action,
        public string $reason,
        public ?string $retentionPolicyId = null,
        public ?string $retentionExceptionUuid = null,
        public bool $awaitingAdministrativePeriod = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resource_type' => $this->resourceType,
            'action' => $this->action->value,
            'reason' => $this->reason,
            'retention_policy_id' => $this->retentionPolicyId,
            'retention_exception_uuid' => $this->retentionExceptionUuid,
            'awaiting_administrative_period' => $this->awaitingAdministrativePeriod,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $action = DeletionResourceAction::tryFrom((string) ($data['action'] ?? ''));

        if ($action === null) {
            throw new InvalidArgumentException('Invalid deletion plan resource action.');
        }

        return new self(
            resourceType: (string) ($data['resource_type'] ?? ''),
            action: $action,
            reason: (string) ($data['reason'] ?? ''),
            retentionPolicyId: isset($data['retention_policy_id']) ? (string) $data['retention_policy_id'] : null,
            retentionExceptionUuid: isset($data['retention_exception_uuid']) ? (string) $data['retention_exception_uuid'] : null,
            awaitingAdministrativePeriod: (bool) ($data['awaiting_administrative_period'] ?? false),
        );
    }
}
