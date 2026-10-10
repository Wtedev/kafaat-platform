<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProgramAcceptancePreviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyHtml,
        public readonly ?string $groupUrl,
        public readonly bool $showPendingLine,
        public readonly string $logoUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.program-registration-approved');
    }
}
