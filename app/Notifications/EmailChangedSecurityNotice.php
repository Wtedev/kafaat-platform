<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangedSecurityNotice extends Notification
{
    use Queueable;

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
            ->subject('تم تغيير البريد الإلكتروني المرتبط بحسابك - منصة كفاءات')
            ->greeting('مرحباً')
            ->line('تم تغيير البريد الإلكتروني المرتبط بحسابك.')
            ->line('إذا لم تقم بهذا التغيير، يرجى التواصل مع إدارة المنصة فوراً.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
