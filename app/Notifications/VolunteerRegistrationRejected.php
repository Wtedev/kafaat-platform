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
            ->subject('Volunteer Registration Update — '.$opportunity->title)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('We regret to inform you that your registration for **'.$opportunity->title.'** could not be approved at this time.');

        if ($this->registration->rejected_reason) {
            $message->line('**Reason:** '.$this->registration->rejected_reason);
        }

        return $message
            ->line('We appreciate your willingness to volunteer and hope to see you at future opportunities.')
            ->salutation('Best regards, Kafaat Team');
    }
}
