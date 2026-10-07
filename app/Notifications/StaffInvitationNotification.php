<?php

namespace App\Notifications;

use App\Services\StaffUi\StaffInvitationService;
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
            ->line('اضغط الزر أدناه لتعيين كلمة المرور وتفعيل حسابك. الرابط صالح لمدة '.$this->expiryLabel().'.')
            ->action('تعيين كلمة المرور', $url)
            ->line('إذا لم تكن تتوقع هذه الدعوة، يمكنك تجاهل الرسالة.')
            ->salutation('مع تحيات فريق كفاءات');
    }

    private function expiryLabel(): string
    {
        $minutes = max(1, (int) config('auth.passwords.'.StaffInvitationService::BROKER.'.expire', 72 * 60));

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? 'ساعة واحدة' : $hours.' ساعة';
        }

        return $minutes.' دقيقة';
    }
}
