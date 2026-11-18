<?php

namespace App\Mail;

use App\Models\ExperienceHouseManager;
use App\Models\ExperienceHouseRequest as R;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** تنبيه الفريق بوصول طلب جديد. */
class ExperienceHouseNewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly R $experienceRequest,
        public readonly ExperienceHouseManager $manager,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'منصة بيوت الخبرة: وصل طلب دعم جديد يحتاج مراجعتك');
    }

    public function content(): Content
    {
        $r = $this->experienceRequest;
        $siteBase = rtrim((string) config('site.website_url', config('app.url')), '/');
        $departments = collect($r->departments ?? [])
            ->map(fn ($d) => $d === 'other' && $r->other_department ? $r->other_department : (R::DEPARTMENTS[$d] ?? $d))
            ->implode('، ');

        return new Content(
            view: 'mail.experience-house-new-request',
            with: [
                'r' => $r,
                'name' => $this->manager->firstName(),
                'urgent' => $r->priority === 'urgent',
                'departments' => $departments,
                'supportTypes' => implode('، ', R::labelsFor($r->support_types, R::SUPPORT_TYPES)),
                'priority' => R::PRIORITIES[$r->priority] ?? '—',
                'url' => route('experience-houses.manage.show', $r),
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail', 'images/brand/kafaat-logo-mail.png'), '/'),
            ],
        );
    }
}
