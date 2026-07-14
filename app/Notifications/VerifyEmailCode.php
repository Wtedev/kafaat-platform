<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCode extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresMinutes,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('رمز التحقق من بريدك الإلكتروني - منصة كفاءات')
            ->greeting('مرحباً')
            ->line('رمز التحقق الخاص بك هو:')
            ->line('**'.$this->code.'**')
            ->line("هذا الرمز صالح لمدة {$this->expiresMinutes} دقيقة.")
            ->line('إذا لم تطلب هذا الرمز فيمكنك تجاهل هذه الرسالة.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
