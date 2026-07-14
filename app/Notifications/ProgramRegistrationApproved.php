<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use App\Support\TrainingProgramExtrasSupport;
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
        $program->loadMissing([]);

        $message = (new MailMessage)
            ->subject('تم قبول تسجيلك — '.$program->title)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».');

        if ($program->start_date) {
            $message->line('تاريخ البدء: '.$program->start_date->format('Y/m/d'));
        }

        $whatsappUrl = TrainingProgramExtrasSupport::whatsappGroupUrlFor($program, $notifiable);
        if ($whatsappUrl !== null) {
            $message->line('رابط مجموعة الواتساب: '.$whatsappUrl);
        }

        $programUrl = filled($program->slug)
            ? route('public.programs.show', $program->slug)
            : route('portal.programs');

        return $message
            ->action('عرض البرنامج', $programUrl)
            ->line('نتطلع لمشاركتك في البرنامج.')
            ->salutation('مع تحيات فريق كفاءات');
    }
}
