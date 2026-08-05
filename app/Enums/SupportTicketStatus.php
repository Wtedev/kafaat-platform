<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnUser = 'waiting_on_user';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::InProgress => 'قيد المعالجة',
            self::WaitingOnUser => 'بانتظار ردك',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    public function adminLabel(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::InProgress => 'قيد المعالجة',
            self::WaitingOnUser => 'بانتظار المستفيد',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Resolved;
    }

    /**
     * Statuses that count toward the one-open-ticket rule.
     *
     * @return list<self>
     */
    public static function openishStatuses(): array
    {
        return [self::Open, self::InProgress, self::WaitingOnUser];
    }

    public function isOpenish(): bool
    {
        return in_array($this, self::openishStatuses(), true);
    }

    /**
     * Chat (beneficiary or staff reply) is allowed while the ticket is openish.
     */
    public function allowsChat(): bool
    {
        return $this->isOpenish();
    }

    public function allowsBeneficiaryReply(): bool
    {
        return $this->allowsChat();
    }

    public function requiresStatusUpdateText(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    /**
     * No reopen: closed/resolved tickets stay terminal; further contact = new ticket.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::WaitingOnUser, self::Resolved, self::Closed],
            self::InProgress => [self::WaitingOnUser, self::Resolved, self::Closed, self::Open],
            self::WaitingOnUser => [self::InProgress, self::Resolved, self::Closed, self::Open],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $this === $to || in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->adminLabel();
        }

        return $out;
    }

    /**
     * Map unknown/legacy values safely without breaking reads.
     */
    public static function coerce(null|string|self $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::Open;
    }
}
