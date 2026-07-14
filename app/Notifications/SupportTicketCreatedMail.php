<?php

namespace App\Notifications;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketCreatedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $adminUrl = SupportTicketResource::getUrl('edit', ['record' => $ticket]);

        return (new MailMessage)
            ->subject('تذكرة دعم جديدة #'.$ticket->getKey().' — '.$ticket->subject)
            ->greeting('مرحباً،')
            ->line('وصلت تذكرة دعم جديدة من الموقع.')
            ->line('الرقم: #'.$ticket->getKey())
            ->line('الاسم: '.$ticket->name)
            ->line('البريد: '.$ticket->email)
            ->line('الموضوع: '.$ticket->subject)
            ->line('الصفحة: '.($ticket->page_url ?: '—'))
            ->line('التفاصيل:')
            ->line($ticket->body)
            ->action('فتح التذكرة في لوحة الإدارة', $adminUrl)
            ->salutation('مع تحيات فريق كفاءات');
    }
}
