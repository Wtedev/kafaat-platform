<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreatedMail;
use Illuminate\Support\Facades\Notification;

final class SupportTicketService
{
    /**
     * @param  array{name: string, email: string, subject: string, body: string, page_url?: string|null}  $data
     */
    public function create(array $data, ?User $user = null): SupportTicket
    {
        $ticket = SupportTicket::query()->create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'page_url' => $data['page_url'] ?? null,
            'status' => SupportTicketStatus::Open,
        ]);

        $this->notifyAdmin($ticket);

        return $ticket;
    }

    private function notifyAdmin(SupportTicket $ticket): void
    {
        $adminEmail = config('app.admin_email');
        if (! filled($adminEmail)) {
            return;
        }

        $adminUser = User::query()->where('email', $adminEmail)->first();
        if ($adminUser !== null) {
            $adminUser->notify(new SupportTicketCreatedMail($ticket));

            return;
        }

        Notification::route('mail', (string) $adminEmail)
            ->notify(new SupportTicketCreatedMail($ticket));
    }
}
