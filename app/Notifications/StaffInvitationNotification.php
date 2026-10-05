<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
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
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('دعوة للانضمام إلى فريق كفاءات')
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تمت دعوتك للانضمام إلى فريق العمل في منصة كفاءات.')
            ->line('اضغط الزر أدناه لتعيين كلمة المرور وتفعيل حسابك. الرابط صالح لمدة ساعة.')
            ->action('تعيين كلمة المرور', $url)
            ->line('إذا لم تكن تتوقع هذه الدعوة، يمكنك تجاهل الرسالة.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
