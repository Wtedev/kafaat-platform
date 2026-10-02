<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSenderType;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SupportInboxListing
{
    public function __construct(
        private readonly SupportUnreadService $unread,
    ) {}

    public function canReply(?User $user): bool
    {
        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.reply'));
    }

    public function canManageStatus(?User $user): bool
    {
        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.manage_status'));
    }

    public function canInternalNotes(?User $user): bool
    {
        return $user instanceof User && ($user->isAdmin() || $user->can('support_tickets.internal_notes'));
    }

    /**
     * @return Collection<int, SupportTicket>
     */
    public function tickets(User $staff, string $filterTab, string $search): Collection
    {
        $query = $this->baseQuery($staff);
        $this->applyFilterTab($query, $staff, $filterTab);
        $this->applySearch($query, $search);

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{all: int, unread: int, open: int, in_progress: int, closed: int}
     */
    public function tabCounts(User $staff, string $search): array
    {
        $make = function () use ($staff, $search): Builder {
            $query = $this->baseQuery($staff);
            $this->applySearch($query, $search);

            return $query;
        };

        return [
            'all' => (clone $make())->count(),
            'unread' => $this->applyUnreadFilter(clone $make(), $staff)->count(),
            'open' => (clone $make())->where('status', SupportTicketStatus::Open->value)->count(),
            'in_progress' => (clone $make())->where('status', SupportTicketStatus::InProgress->value)->count(),
            'closed' => (clone $make())->whereIn('status', [
                SupportTicketStatus::Closed->value,
                SupportTicketStatus::Resolved->value,
            ])->count(),
        ];
    }

    /**
     * @return array{all: int, unread: int, open: int, in_progress: int, closed: int}
     */
    public function emptyTabCounts(): array
    {
        return ['all' => 0, 'unread' => 0, 'open' => 0, 'in_progress' => 0, 'closed' => 0];
    }

    private function baseQuery(User $staff): Builder
    {
        $query = SupportTicket::query()->with(['assignee:id,name', 'latestMessage']);
        $this->unread->attachUnreadBeneficiarySelect($query, $staff);

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('ticket_number', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }

    private function applyFilterTab(Builder $query, User $staff, string $filterTab): void
    {
        match ($filterTab) {
            'unread' => $this->applyUnreadFilter($query, $staff),
            'open' => $query->where('status', SupportTicketStatus::Open->value),
            'in_progress' => $query->where('status', SupportTicketStatus::InProgress->value),
            'closed' => $query->whereIn('status', [
                SupportTicketStatus::Closed->value,
                SupportTicketStatus::Resolved->value,
            ]),
            default => $query,
        };
    }

    private function applyUnreadFilter(Builder $query, User $staff): Builder
    {
        return $query->whereRaw(
            '(SELECT COUNT(*) FROM support_ticket_messages m WHERE m.support_ticket_id = support_tickets.id AND m.sender_type = ? AND m.id > COALESCE((SELECT c.last_read_message_id FROM support_ticket_read_cursors c WHERE c.support_ticket_id = support_tickets.id AND c.user_id = ?), 0)) > 0',
            [SupportMessageSenderType::Beneficiary->value, $staff->id]
        );
    }
}
