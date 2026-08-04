<?php

namespace App\Services\Support;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\SupportMessageSenderType;
use App\Inbox\NotificationMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketCreatedMail;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Inbox\UserNotificationPreferences;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SupportNotificationService
{
    public function __construct(
        private readonly InboxNotificationService $inbox,
        private readonly UserNotificationPreferences $prefs,
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

    public function notifyBeneficiaryOfSupportReply(
        SupportTicket $ticket,
        SupportTicketMessage $message,
        User $actor,
    ): void {
        if ($message->sender_type !== SupportMessageSenderType::Support
            && $message->sender_type !== SupportMessageSenderType::System) {
            return;
        }

        $beneficiary = $ticket->user;
        if ($beneficiary === null) {
            return;
        }

        // Never notify the actor about their own message.
        if ((int) $beneficiary->id === (int) $actor->id) {
            return;
        }

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
            emailable: true,
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

    /**
     * Dedicated check used by docs/tests: support email requires master notify_email
     * AND notification_settings.support_replies_email (or categories.support.email).
     * Default when unset: false (opt-in).
     */
    public function wantsSupportReplyEmail(User $user): bool
    {
        return $this->prefs->wantsSupportRepliesEmail($user);
    }
}
