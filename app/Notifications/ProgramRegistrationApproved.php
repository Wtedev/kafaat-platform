<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\ProgramApprovalMail;
use App\Support\ProgramRegistrationSuccessPresenter;
use App\Support\QrCodeImage;
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
        $this->registration->loadMissing(['trainingProgram', 'user.profile']);
        $program = $this->registration->trainingProgram;
        $user = $notifiable instanceof User ? $notifiable : $this->registration->user;
        if ($user instanceof User) {
            $user->loadMissing('profile');
        }

        $subject = ProgramApprovalMail::subjectFor($program);
        $groupUrl = ProgramApprovalMail::groupUrl($program, $user instanceof User ? $user : null);
        $custom = filled($program->approval_message);
        $inPerson = $program->delivery_mode?->hasPhysicalComponent() ?? false;

        if ($custom || $program->whatsapp_groups_enabled || $inPerson) {
            $defaultBody = '<p style="margin:0 0 16px;text-align:center;">تم قبول طلبك في البرنامج التدريبي «'.e($program->title).'».</p>'
                .($program->start_date ? '<p style="margin:0 0 16px;text-align:center;">تاريخ البدء: '.e($program->start_date->format('Y/m/d')).'</p>' : '');

            return (new MailMessage)
                ->subject($subject)
                ->view('mail.program-registration-approved', [
                    'subjectLine' => $subject,
                    'bodyHtml' => $custom ? (string) $program->approval_message : $defaultBody,
                    // In-person programs: the QR the prep officer scans at the door.
                    'qrPng' => $inPerson ? QrCodeImage::png(ProgramRegistrationSuccessPresenter::qrPayload($program, $this->registration), 280) : null,
                    'passCode' => $inPerson ? ProgramRegistrationSuccessPresenter::passCode($program, $this->registration) : null,
                    'groupUrl' => $groupUrl,
                    'showPendingLine' => $program->whatsapp_groups_enabled
                        && $groupUrl === null
                        && ProgramApprovalMail::genderUnspecified($user instanceof User ? $user : null),
                    'logoUrl' => rtrim((string) config('site.website_url'), '/').'/'.ltrim((string) config('brand.logos.kafaat_mail'), '/'),
                ]);
        }

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».');

        if ($program->start_date) {
            $message->line('تاريخ البدء: '.$program->start_date->format('Y/m/d'));
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
