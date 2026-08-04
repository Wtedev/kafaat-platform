<?php

namespace App\Services\Support;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\SupportMessageSenderType;
use App\Inbox\NotificationMessage;
use App\Jobs\SendSupportReplyEmailJob;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketCreatedMail;
use App\Services\Inbox\InboxNotificationService;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SupportNotificationService
{
    public function __construct(
        private readonly InboxNotificationService $inbox,
    ) {}

    public function notifyAdminsOfNewTicket(SupportTicket $ticket): void
    {
        try {
            $adminEmail = EmailNormalizer::normalize((string) config('app.admin_email', ''));
            if ($adminEmail === '') {
                return;
            }

            // Users store emails via EmailNormalizer mutator; match on normalized config.
            $adminUser = User::query()->where('email', $adminEmail)->first();
            if ($adminUser !== null) {
                $adminUser->notify(new SupportTicketCreatedMail($ticket));

                return;
            }

            Notification::route('mail', $adminEmail)
                ->notify(new SupportTicketCreatedMail($ticket));
        } catch (Throwable $e) {
            Log::warning('support.ticket_created_mail_failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * In-app inbox + queued Resend email for a real staff textual reply.
     * Email is always attempted (not gated on support_replies_email / notify_email).
     * Mail failure must not roll back the saved reply (dispatch is outside the write txn).
     */
    public function notifyBeneficiaryOfSupportReply(
        SupportTicket $ticket,
        SupportTicketMessage $message,
        User $actor,
    ): void {
        if ($message->sender_type !== SupportMessageSenderType::Support) {
            return;
        }

        if ($message->is_system) {
            return;
        }

        $beneficiary = $ticket->user;
        if ($beneficiary !== null && (int) $beneficiary->id === (int) $actor->id) {
            return;
        }

        if ($beneficiary !== null) {
            $title = 'رد جديد على تذكرة الدعم '.$ticket->displayNumber();
            $body = 'وصل رد من فريق الدعم على محادثتك «'.$ticket->subject.'».';

            $msg = new NotificationMessage(
                type: InboxNotificationType::SupportReply,
                title: $title,
                message: $body,
                senderId: null,
                targetType: NotificationTargetType::SingleUser,
                context: [
                    'resource' => 'support_ticket',
                    'id' => (int) $ticket->getKey(),
                ],
                // Primary staff-reply email is handled by SendSupportReplyEmailJob —
                // never the preference-gated InboxNotificationEmail path.
                emailable: false,
            );

            try {
                $this->inbox->dispatch($msg, [$beneficiary->id]);
            } catch (Throwable $e) {
                Log::warning('support.reply_inbox_failed', [
                    'ticket_id' => $ticket->id,
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            // Caller (addSupportReply) already persisted the message outside its write txn.
            SendSupportReplyEmailJob::dispatch($message->id);
        } catch (Throwable $e) {
            Log::warning('support.reply_email_dispatch_failed', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'exception_class' => $e::class,
            ]);
        }
    }
}
