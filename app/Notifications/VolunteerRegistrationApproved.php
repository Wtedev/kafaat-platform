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
            ->subject('Your Volunteer Registration Has Been Approved — ' . $opportunity->title)
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('We are pleased to confirm your registration for the volunteer opportunity **' . $opportunity->title . '** has been approved.');

        if ($opportunity->start_date) {
            $message->line('**Start Date:** ' . $opportunity->start_date->format('F j, Y'));
        }

        if ($opportunity->hours_expected) {
            $message->line('**Expected Volunteer Hours:** ' . $opportunity->hours_expected . ' hours');
        }

        return $message
            ->action('View Opportunity Details', url('/'))
            ->line('Thank you for contributing your time and effort!')
            ->salutation('Best regards, Kafaat Team');
    }
}
