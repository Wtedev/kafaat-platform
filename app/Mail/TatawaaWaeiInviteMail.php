<?php

namespace App\Mail;

use App\Support\TatawaaWaeiInviteMail as Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TatawaaWaeiInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ?string $recipientName = null,
        public readonly bool $reminder = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Invite::subject($this->reminder));
    }

    public function headers(): Headers
    {
        // Keep this as a normal transactional message — avoid promo/list markers.
        return new Headers(
            text: [
                'X-Priority' => '3 (Normal)',
                'Importance' => 'Normal',
            ],
        );
    }

    public function content(): Content
    {
        $siteBase = rtrim((string) config('site.website_url'), '/');

        return new Content(
            view: 'mail.tatawaa-waei-invite',
            with: [
                'subjectLine' => Invite::subject($this->reminder),
                'registerUrl' => Invite::registerUrl(),
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'),
                // Public HTTPS URL — Gmail blocks data: URIs from render()/embed().
                'heroSrc' => Invite::heroPublicUrl(),
                'openingLine' => Invite::openingLine($this->recipientName, $this->reminder),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = Invite::heroAbsolutePath();
        if ($path === null) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as('tatawaa-waei-invite.jpg')
                ->withMime('image/jpeg'),
        ];
    }
}
