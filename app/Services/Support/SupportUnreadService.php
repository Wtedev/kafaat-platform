<?php

namespace App\Services\Support;

use App\Enums\SupportMessageSenderType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketReadCursor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SupportUnreadService
{
    /**
     * Count unread support-side messages across all of the user's tickets.
     */
    public function unreadSupportReplyCount(User $user): int
    {
        $ticketIds = SupportTicket::query()
            ->ownedBy($user)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return 0;
        }

        $cursors = SupportTicketReadCursor::query()
            ->where('user_id', $user->id)
            ->whereIn('support_ticket_id', $ticketIds)
            ->pluck('last_read_message_id', 'support_ticket_id');

        $total = 0;

        foreach ($ticketIds as $ticketId) {
            $lastRead = (int) ($cursors[$ticketId] ?? 0);

            $total += SupportTicketMessage::query()
                ->where('support_ticket_id', $ticketId)
                ->visibleToBeneficiary()
                ->fromSupport()
                ->when($lastRead > 0, fn ($q) => $q->where('id', '>', $lastRead))
                ->count();
        }

        return $total;
    }

    public function unreadCountForTicket(SupportTicket $ticket, User $user): int
    {
        $lastRead = (int) (SupportTicketReadCursor::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('user_id', $user->id)
            ->value('last_read_message_id') ?? 0);

        return SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->visibleToBeneficiary()
            ->fromSupport()
            ->when($lastRead > 0, fn ($q) => $q->where('id', '>', $lastRead))
            ->count();
    }

    /**
     * Unread beneficiary messages for staff inbox sorting/badges.
     */
    public function unreadBeneficiaryCountForTicket(SupportTicket $ticket, ?User $staff = null): int
    {
        $lastRead = 0;

        if ($staff !== null) {
            $lastRead = (int) (SupportTicketReadCursor::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('user_id', $staff->id)
                ->value('last_read_message_id') ?? 0);
        }

        return SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->fromBeneficiary()
            ->when($lastRead > 0, fn ($q) => $q->where('id', '>', $lastRead))
            ->count();
    }

    public function markTicketRead(SupportTicket $ticket, User $user, ?string $perspective = 'beneficiary'): void
    {
        $query = SupportTicketMessage::query()
            ->where('support_ticket_id', $ticket->id);

        if ($perspective === 'beneficiary') {
            $query->visibleToBeneficiary();
        }

        $lastId = $query->max('id');

        if ($lastId === null) {
            return;
        }

        SupportTicketReadCursor::query()->updateOrCreate(
            [
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
            ],
            [
                'last_read_message_id' => (int) $lastId,
                'last_read_at' => now(),
            ],
        );
    }

    /**
     * Efficient SQL for admin list: unread beneficiary message count since staff cursor
     * (or all beneficiary messages if no cursor). Used as subquery select.
     */
    public function attachUnreadBeneficiarySelect($query, User $staff): void
    {
        $staffId = (int) $staff->id;
        $beneficiary = SupportMessageSenderType::Beneficiary->value;

        $query->addSelect([
            DB::raw(
                '(SELECT COUNT(*) FROM support_ticket_messages AS m
                  WHERE m.support_ticket_id = support_tickets.id
                    AND m.sender_type = '.DB::getPdo()->quote($beneficiary).'
                    AND m.id > COALESCE((
                        SELECT c.last_read_message_id
                        FROM support_ticket_read_cursors AS c
                        WHERE c.support_ticket_id = support_tickets.id
                          AND c.user_id = '.(int) $staffId.'
                    ), 0)
                ) AS unread_beneficiary_count'
            ),
        ]);
    }
}
