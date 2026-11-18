<?php

namespace App\Mail;

use App\Models\ExperienceHouseRequest as R;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** إشعار الجمعية المستفيدة بتحديث حالة طلبها. */
class ExperienceHouseStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    /** العنوان والنص لكل حالة. */
    public const MESSAGES = [
        'in_review' => ['بدأنا دراسة طلبكم', 'استلم فريق كفاءات طلبكم وبدأ دراسته، وسنتواصل معكم قريباً لترتيب الخطوات القادمة.'],
        'scheduled' => ['تم تحديد موعد لطلبكم', 'تمت جدولة الدعم المطلوب، وسيتواصل معكم الفريق لتأكيد التفاصيل والموعد.'],
        'in_progress' => ['بدأ تنفيذ الدعم', 'بدأ فريق كفاءات تنفيذ الدعم المطلوب لجمعيتكم. نتمنى لكم الفائدة.'],
        'completed' => ['اكتمل طلبكم', 'اكتمل تنفيذ الدعم المطلوب. شكراً لثقتكم بجمعية كفاءات، ونسعد بخدمتكم دائماً.'],
        'rejected' => ['بخصوص طلبكم', 'نشكر لكم تواصلكم، ونعتذر عن عدم إمكانية تلبية هذا الطلب حالياً. يسعدنا استقبال طلباتكم مستقبلاً.'],
    ];

    public function __construct(public readonly R $experienceRequest) {}

    public static function supports(string $status): bool
    {
        return array_key_exists($status, self::MESSAGES);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::MESSAGES[$this->experienceRequest->status][0].' — '.$this->experienceRequest->reference_no);
    }

    public function content(): Content
    {
        $r = $this->experienceRequest;
        $siteBase = rtrim((string) config('site.website_url', config('app.url')), '/');
        [$headline, $bodyText] = self::MESSAGES[$r->status];

        return new Content(
            view: 'mail.experience-house-status',
            with: [
                'r' => $r,
                'name' => $r->contact_name,
                'headline' => $headline,
                'bodyText' => $bodyText,
                'statusLabel' => R::STATUSES[$r->status] ?? $r->status,
                'logoUrl' => $siteBase.'/'.ltrim((string) config('brand.logos.kafaat_mail', 'images/brand/kafaat-logo-mail.png'), '/'),
            ],
        );
    }
}
