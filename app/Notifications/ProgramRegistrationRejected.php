<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProgramRegistrationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ProgramRegistration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $program = $this->registration->trainingProgram;

        $message = (new MailMessage)
            ->subject('تحديث بشأن تسجيلك — '.$program->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('نأسف لإبلاغك أن طلب تسجيلك في البرنامج «'.$program->title.'» لم يُقبل في الوقت الحالي.');

        if ($this->registration->rejected_reason) {
            $message->line('السبب: '.$this->registration->rejected_reason);
        }

        return $message
            ->line('إذا كان لديك أي استفسار، تواصل مع فريق الدعم.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
