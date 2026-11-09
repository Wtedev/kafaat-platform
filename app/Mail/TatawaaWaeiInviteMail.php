<?php

namespace App\Mail;

use App\Support\TatawaaWaeiInviteMail as Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TatawaaWaeiInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: Invite::SUBJECT);
    }

    public function content(): Content
    {
        $siteBase = rtrim((string) config('site.website_url'), '/');

        return new Content(
            view: 'mail.tatawaa-waei-invite',
            with: [
                'subjectLine' => Invite::SUBJECT,
                'registerUrl' => Invite::registerUrl(),
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'),
                'heroUrl' => $siteBase.'/'.ltrim(Invite::HERO_PUBLIC_PATH, '/'),
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
