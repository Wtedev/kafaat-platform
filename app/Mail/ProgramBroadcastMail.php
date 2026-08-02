<?php

namespace App\Mail;

use App\Models\ProgramBroadcast;
use App\Models\ProgramBroadcastRecipient;
use App\Support\RichContentSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Per-recipient program broadcast email. Sent synchronously inside a queued job
 * (never bulk To/CC/BCC). Does not implement ShouldQueue itself.
 */
class ProgramBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ProgramBroadcast $broadcast,
        public readonly ProgramBroadcastRecipient $recipient,
        public readonly string $programTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->broadcast->subject,
        );
    }

    public function content(): Content
    {
        $name = trim((string) ($this->recipient->name ?: 'المستفيد'));

        return new Content(
            markdown: 'emails.program-broadcast',
            with: [
                'greeting' => 'مرحباً '.$name.'،',
                'programTitle' => $this->programTitle,
                'subject' => $this->broadcast->subject,
                'contentHtml' => RichContentSupport::toDisplayHtml($this->broadcast->content),
            ],
        );
    }
}
