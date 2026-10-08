<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\DataForumAcceptance;
use App\Support\DataForumTelegramReminder as Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataForumTelegramReminder extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $whenWord,
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
            ->subject(Reminder::SUBJECT)
            ->view('mail.data-forum-telegram-reminder', [
                'telegramUrl' => DataForumAcceptance::telegramUrlFor($gender),
                'logoUrl' => DataForumAcceptance::logoUrl(),
                'whenWord' => $this->whenWord,
            ]);
    }
}
