<?php

namespace App\Notifications;

use App\Models\TrainingProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCheckerInviteCode extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresMinutes,
        public readonly TrainingProgram $program,
        public readonly string $gateUrl,
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
        $name = property_exists($notifiable, 'name') ? (string) $notifiable->name : '';

        return (new MailMessage)
            ->subject('رمز دخول بوابة التحضير — '.$this->program->title)
            ->greeting($name !== '' ? 'مرحباً '.$name.'،' : 'مرحباً،')
            ->line('تمّت دعوتك لتحضير الحضور في البرنامج «'.$this->program->title.'».')
            ->line('رمز التحقق الخاص بك هو:')
            ->line('**'.$this->code.'**')
            ->line("هذا الرمز صالح لمدة {$this->expiresMinutes} دقيقة.")
            ->action('دخول بوابة التحضير', $this->gateUrl)
            ->line('إذا لم تتوقعي هذه الدعوة فيمكنك تجاهل الرسالة.')
            ->salutation('فريق منصة كفاءات');
    }
}
