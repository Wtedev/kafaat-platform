<?php

namespace App\Mail;

use App\Models\ExperienceHouseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExperienceHouseAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExperienceHouseManager $manager,
        public readonly string $url,
        public readonly string $purpose,
        public readonly ?ExperienceHouseManager $inviter = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'invite'
            ? 'فعّل حسابك في طلبات بيوت الخبرة — جمعية كفاءات'
            : 'إنشاء كلمة مرور — طلبات بيوت الخبرة');
    }

    public function content(): Content
    {
        $siteBase = rtrim((string) config('site.website_url', config('app.url')), '/');

        return new Content(
            view: 'mail.experience-house-access',
            with: [
                'invite' => $this->purpose === 'invite',
                'name' => $this->manager->firstName(),
                'url' => $this->url,
                'inviterName' => $this->inviter?->name,
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail', 'images/brand/kafaat-logo-mail.png'), '/'),
            ],
        );
    }
}
