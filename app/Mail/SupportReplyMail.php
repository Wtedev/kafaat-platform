<?php

namespace App\Mail;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Staff support-reply email. Sent synchronously inside SendSupportReplyEmailJob
 * (job is queued; this mailable is not). Uses the official Laravel markdown mail
 * theme (same visual identity as other Kafaat mail).
 */
class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
        public readonly string $recipientName,
        public readonly string $ticketUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رد جديد على تذكرة الدعم رقم '.$this->ticket->displayNumber(),
        );
    }

    public function content(): Content
    {
        $status = SupportTicketStatus::coerce($this->ticket->status);

        return new Content(
            markdown: 'emails.support-reply',
            with: [
                'greeting' => 'مرحباً '.$this->recipientName.'،',
                'ticketNumber' => $this->ticket->displayNumber(),
                'ticketSubject' => $this->ticket->subject,
                'ticketStatus' => $status->label(),
                'replyBody' => $this->message->body,
                'ticketUrl' => $this->ticketUrl,
            ],
        );
    }
}
