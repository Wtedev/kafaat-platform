<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * نسخة بريدية عامة لأي تنبيه داخل الموقع، تحترم تفضيل المستخدم (notify_email).
 */
class InboxNotificationEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $titleText,
        public readonly string $bodyText,
        public readonly ?string $actionUrl = null,
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
        $mail = (new MailMessage)
            ->subject($this->titleText)
            ->greeting('مرحباً '.($notifiable->name ?? '').'،');

        foreach (preg_split('/\r\n|\r|\n/', $this->bodyText) ?: [$this->bodyText] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $mail->line($line);
            }
        }

        if ($this->actionUrl !== null) {
            $mail->action('عرض التفاصيل', $this->actionUrl);
        }

        return $mail
            ->line('يمكنك إدارة تفضيلات التنبيهات من إعدادات حسابك.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
