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
        return $this === self::Closed;
    }

    public function allowsBeneficiaryReply(): bool
    {
        return ! in_array($this, [self::Closed, self::Resolved], true);
    }

    public function requiresStatusUpdateText(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::WaitingOnUser, self::Resolved, self::Closed],
            self::InProgress => [self::WaitingOnUser, self::Resolved, self::Closed, self::Open],
            self::WaitingOnUser => [self::InProgress, self::Resolved, self::Closed, self::Open],
            self::Resolved => [self::Closed, self::Open, self::InProgress],
            self::Closed => [self::Open, self::InProgress],
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
