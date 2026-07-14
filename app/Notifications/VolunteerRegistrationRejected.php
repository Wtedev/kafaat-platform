<?php

namespace App\Notifications;

use App\Models\VolunteerRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerRegistrationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly VolunteerRegistration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $opportunity = $this->registration->opportunity;

        $message = (new MailMessage)
            ->subject('تحديث بشأن تسجيلك التطوعي — '.$opportunity->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('نأسف لإبلاغك أن طلب تسجيلك في الفرصة التطوعية «'.$opportunity->title.'» لم يُقبل في الوقت الحالي.');

        if ($this->registration->rejected_reason) {
            $message->line('السبب: '.$this->registration->rejected_reason);
        }

        return $message
            ->line('نقدر رغبتك في التطوع ونتطلع لمشاركتك في فرص قادمة.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
