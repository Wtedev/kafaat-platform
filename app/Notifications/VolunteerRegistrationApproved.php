<?php

namespace App\Notifications;

use App\Models\VolunteerRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VolunteerRegistrationApproved extends Notification implements ShouldQueue
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
            ->subject('تم قبول تسجيلك التطوعي — '.$opportunity->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تم قبول طلبك في الفرصة التطوعية «'.$opportunity->title.'».');

        if ($opportunity->start_date) {
            $message->line('تاريخ البدء: '.$opportunity->start_date->format('Y/m/d'));
        }

        if ($opportunity->hours_expected) {
            $message->line('الساعات المتوقعة: '.$opportunity->hours_expected.' ساعة');
        }

        return $message
            ->action('عرض الفرص التطوعية في بوابتي', route('portal.volunteering'))
            ->line('شكراً لمساهمتك بوقتك وجهدك.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
