<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProgramRegistrationApproved extends Notification implements ShouldQueue
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
            ->subject('Your Registration Has Been Approved — '.$program->title)
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your registration for the training program **'.$program->title.'** has been approved.');

        if ($program->start_date) {
            $message->line('**Start Date:** '.$program->start_date->format('F j, Y'));
        }

        return $message
            ->action('View Program Details', url('/'))
            ->line('We look forward to seeing you participate.')
            ->salutation('Best regards, Kafaat Team');
    }
}
