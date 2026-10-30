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
        public readonly ?string $subjectLine = null,
        public readonly ?string $introLine = null,
        public readonly ?string $buttonLabel = null,
        public readonly ?string $buttonUrl = null,
        public readonly bool $attachPdf = false,
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
        if ($this->subjectLine !== null) {
            return $this->programMail($notifiable);
        }

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

    private function programMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subjectLine ?? ('شهادتك جاهزة — '.$this->activityLabel))
            ->greeting('مرحباً '.($notifiable->name ?? '').'،')
            ->line($this->introLine ?? ('صدرت شهادتك المتعلقة بـ «'.$this->activityLabel.'».'));

        if ($this->buttonLabel !== null && $this->buttonUrl !== null) {
            $mail->action($this->buttonLabel, $this->buttonUrl);
        }

        if ($this->attachPdf) {
            $path = $this->certificate->absolutePath();
            if (is_string($path) && is_file($path)) {
                $mail->attach($path, [
                    'as' => $this->certificate->certificate_number.'.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail->salutation('مع تحيات فريق كفاءات');
    }
}
