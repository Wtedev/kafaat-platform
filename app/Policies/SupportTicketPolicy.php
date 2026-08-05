<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        return $ticket->user_id !== null && (int) $ticket->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isPortalUser() || $user->isAdminOrStaff();
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        // Ownership only — closed/resolved rejection is handled in the service with a clear message.
        return $ticket->user_id !== null
            && (int) $ticket->user_id === (int) $user->id;
    }

    public function assign(User $user, SupportTicket $ticket): bool
    {
        return $user->isAdmin() || $user->can('support_tickets.assign');
    }

    public function updateStatus(User $user, SupportTicket $ticket): bool
    {
        return $user->isAdmin() || $user->can('support_tickets.manage_status');
    }

    public function manageInternalNotes(User $user, SupportTicket $ticket): bool
    {
        return $user->isAdmin() || $user->can('support_tickets.internal_notes');
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return $user->isAdmin();
    }

    private function canManage(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('support_tickets.view')
            || $user->can('support_tickets.reply');
    }
}
