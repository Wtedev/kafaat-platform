<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateReadyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Certificate $certificate,
        public readonly string $activityLabel,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $downloadUrl = $this->certificate->downloadUrl();
        $verifyUrl = route('certificates.verify', $this->certificate->verification_code);

        $mail = (new MailMessage)
            ->subject('شهادتك جاهزة — '.$this->activityLabel)
            ->greeting('مرحباً '.($notifiable->name ?? '').'،')
            ->line('صدرت شهادتك المتعلقة بـ «'.$this->activityLabel.'».')
            ->line('رقم الشهادة: '.$this->certificate->certificate_number);

        if ($downloadUrl !== null) {
            $mail->action('تحميل الشهادة', $downloadUrl)
                ->line('رابط التحقق من الشهادة: '.$verifyUrl);
        } else {
            $mail->action('التحقق من الشهادة', $verifyUrl);
        }

        return $mail
            ->line('يمكنك أيضاً استعراض شهاداتك من بوابة المستفيد.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
