<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffEmailChangedByAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $oldEmail,
        public readonly string $newEmail,
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
        return (new MailMessage)
            ->subject('تم تغيير البريد الإلكتروني لحسابك')
            ->greeting('مرحباً،')
            ->line('قام أحد المشرفين بتغيير البريد الإلكتروني المرتبط بحسابك في منصة كفاءات.')
            ->line('البريد السابق: '.$this->oldEmail)
            ->line('البريد الجديد: '.$this->newEmail)
            ->line('إذا لم تكن تتوقع هذا التغيير، تواصل مع إدارة المنصة.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
