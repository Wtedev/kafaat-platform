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
        return new Envelope(subject: $this->isActivation()
            ? 'تفعيل حسابك في منصة طلبات الدعم - بيوت الخبرة'
            : 'إعادة تعيين كلمة المرور - منصة طلبات الدعم - بيوت الخبرة');
    }

    /** دعوة جديدة، أو حساب لم يُنشئ كلمة مرور بعد (مثل أول مسؤولة). */
    public function isActivation(): bool
    {
        return $this->purpose === 'invite' || $this->manager->password === null;
    }

    public function content(): Content
    {
        $siteBase = rtrim((string) config('site.website_url', config('app.url')), '/');

        return new Content(
            view: 'mail.experience-house-access',
            with: [
                'invite' => $this->isActivation(),
                'longLink' => $this->purpose === 'invite',
                'name' => $this->manager->firstName(),
                'url' => $this->url,
                'inviterName' => $this->inviter?->name,
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail', 'images/brand/kafaat-logo-mail.png'), '/'),
            ],
        );
    }
}
