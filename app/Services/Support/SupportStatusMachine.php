<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use InvalidArgumentException;

final class SupportStatusMachine
{
    public function assertCanTransition(SupportTicketStatus $from, SupportTicketStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                "Cannot transition support ticket from {$from->value} to {$to->value}."
            );
        }
    }

    public function requiresStatusUpdateText(SupportTicketStatus $to): bool
    {
        return $to->requiresStatusUpdateText();
    }
}
