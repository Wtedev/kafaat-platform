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
            ->subject('Registration Update — ' . $path->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('We regret to inform you that your registration for **' . $path->title . '** could not be approved at this time.');

        if ($this->registration->rejected_reason) {
            $message->line('**Reason:** ' . $this->registration->rejected_reason);
        }

        return $message
            ->line('If you believe this is an error or would like more information, please contact our support team.')
            ->salutation('Best regards, Kafaat Team');
    }
}
