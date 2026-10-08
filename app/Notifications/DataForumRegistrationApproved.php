<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\DataForumAcceptance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataForumRegistrationApproved extends Notification implements ShouldQueue
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
        $gender = null;
        if ($notifiable instanceof User) {
            $notifiable->loadMissing('profile');
            $gender = $notifiable->profile?->gender;
        }

        return (new MailMessage)
            ->subject(DataForumAcceptance::SUBJECT)
            ->markdown('mail.data-forum-registration-approved', [
                'telegramUrl' => DataForumAcceptance::telegramUrlFor($gender),
            ]);
    }
}
