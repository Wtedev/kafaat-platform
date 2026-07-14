<?php

namespace App\Notifications;

use App\Models\PathRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PathRegistrationRejected extends Notification implements ShouldQueue
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

        $message = (new MailMessage)
            ->subject('تحديث بشأن تسجيلك — '.$path->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('نأسف لإبلاغك أن طلب تسجيلك في المسار «'.$path->title.'» لم يُقبل في الوقت الحالي.');

        if ($this->registration->rejected_reason) {
            $message->line('السبب: '.$this->registration->rejected_reason);
        }

        return $message
            ->line('إذا كنت تعتقد أن هناك خطأ أو ترغب بمزيد من التفاصيل، تواصل مع فريق الدعم.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
