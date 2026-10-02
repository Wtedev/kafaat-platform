<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use App\Support\TrainingProgramExtrasSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProgramRegistrationReceived extends Notification implements ShouldQueue
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
        $notice = TrainingProgramExtrasSupport::registrationReceivedNotice($program)
            ?? 'تم استلام طلب مشاركتك، وسيُبلغ المقبولون بعد مراجعة الطلبات واستكمال إجراءات الفرز.';

        $programUrl = filled($program->slug)
            ? route('public.programs.show', $program->slug)
            : route('portal.programs');

        return (new MailMessage)
            ->subject('تم استلام طلب مشاركتك — '.$program->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line($notice)
            ->action('عرض البرنامج', $programUrl)
            ->salutation('مع تحيات فريق كفاءات');
    }
}
