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
            ->subject('Your Registration Has Been Approved — ' . $path->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('Great news! Your registration for the learning path **' . $path->title . '** has been approved.')
            ->line('You can now access the path and begin your courses.')
            ->action('View Your Path', url('/'))
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Best regards, Kafaat Team');
    }
}
