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
            ->subject('Registration Update — '.$program->title)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('We regret to inform you that your registration for **'.$program->title.'** could not be approved at this time.');

        if ($this->registration->rejected_reason) {
            $message->line('**Reason:** '.$this->registration->rejected_reason);
        }

        return $message
            ->line('If you have any questions, please contact our support team.')
            ->salutation('Best regards, Kafaat Team');
    }
}
