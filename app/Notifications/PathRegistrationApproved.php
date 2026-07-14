<?php

namespace App\Notifications;

use App\Models\PathRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PathRegistrationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PathRegistration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->registration->learningPath;

        return (new MailMessage)
            ->subject('تم قبول تسجيلك — '.$path->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تم قبول طلبك في المسار التعليمي «'.$path->title.'».')
            ->line('يمكنك الآن الوصول إلى المسار والبدء في برامجه.')
            ->action('عرض المسار في بوابتي', route('portal.paths.show', $path))
            ->line('إذا كان لديك أي استفسار، تواصل مع فريق الدعم.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
