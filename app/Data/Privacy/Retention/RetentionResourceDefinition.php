<?php

namespace App\Data\Privacy\Retention;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;

final readonly class RetentionResourceDefinition
{
    /**
     * @param  list<RetentionTriggerEvent>  $supportedTriggers
     * @param  list<RetentionPolicyAction>  $supportedActions
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $handlerClass,
        public array $supportedTriggers,
        public array $supportedActions,
        public bool $hasFiles,
        public bool $allowsDelete,
        public bool $allowsAnonymize,
        public bool $requiresManualApproval,
        public bool $needsBackupRunbook,
        public bool $allowsLegalHold,
        public bool $schedulable,
    ) {}

    public function supportsTrigger(RetentionTriggerEvent $trigger): bool
    {
        return in_array($trigger, $this->supportedTriggers, true)
            || in_array($this->aliasTrigger($trigger), $this->supportedTriggers, true);
    }

    public function supportsAction(RetentionPolicyAction $action): bool
    {
        return in_array($action, $this->supportedActions, true);
    }

    private function aliasTrigger(RetentionTriggerEvent $trigger): RetentionTriggerEvent
    {
        return match ($trigger) {
            RetentionTriggerEvent::LastUpdatedAt => RetentionTriggerEvent::UpdatedAt,
            RetentionTriggerEvent::RequestCompletedAt => RetentionTriggerEvent::CompletedAt,
            RetentionTriggerEvent::AccountDeletedAt => RetentionTriggerEvent::AccountAnonymizedAt,
            default => $trigger,
        };
    }
}
