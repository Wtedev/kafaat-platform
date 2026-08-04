<?php

namespace App\Jobs;

use App\Enums\SupportMessageSenderType;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicketMessage;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Emails the beneficiary a staff support reply via the configured mailer (Resend in prod).
 * Idempotent: unique per message id + beneficiary_email_sent_at after successful send.
 */
class SendSupportReplyEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [15, 30, 60, 120, 300];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $supportTicketMessageId,
    ) {}

    public function uniqueId(): string
    {
        return 'support-reply-email:'.$this->supportTicketMessageId;
    }

    public function handle(): void
    {
        $message = SupportTicketMessage::query()
            ->with(['ticket.user'])
            ->find($this->supportTicketMessageId);

        if ($message === null) {
            return;
        }

        if ($message->beneficiary_email_sent_at !== null) {
            return;
        }

        if ($message->sender_type !== SupportMessageSenderType::Support || $message->is_system) {
            return;
        }

        $ticket = $message->ticket;
        if ($ticket === null) {
            return;
        }

        $user = $ticket->user;
        $toEmail = EmailNormalizer::normalize((string) ($user?->email ?: $ticket->email));
        if ($toEmail === '') {
            Log::info('support.reply_email_skipped_no_recipient', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
            ]);

            return;
        }

        $recipientName = trim((string) ($user?->name ?: $ticket->name)) ?: 'المستفيد';
        $ticketUrl = route('portal.support.show', $ticket);

        try {
            Mail::to($toEmail)->send(new SupportReplyMail(
                ticket: $ticket,
                message: $message,
                recipientName: $recipientName,
                ticketUrl: $ticketUrl,
            ));
        } catch (TransportExceptionInterface $e) {
            Log::warning('support.reply_email_transport_failed', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'exception_class' => $e::class,
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::warning('support.reply_email_failed', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'exception_class' => $e::class,
            ]);

            throw $e;
        }

        // Mark only after successful send so retries can re-attempt on failure.
        SupportTicketMessage::query()
            ->whereKey($message->id)
            ->whereNull('beneficiary_email_sent_at')
            ->update(['beneficiary_email_sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('support.reply_email_job_exhausted', [
            'message_id' => $this->supportTicketMessageId,
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['support-reply-email', 'support-message:'.$this->supportTicketMessageId];
    }
}
