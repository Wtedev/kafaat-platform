<?php

namespace App\Services\Certificates;

use App\Enums\InboxNotificationType;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\EmailLog;
use App\Models\InboxNotification;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\CertificateReadyEmail;
use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use App\Services\EmailLogService;
use App\Services\Inbox\InboxNotificationService;
use App\Services\ProgramRegistrationService;
use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ProgramCertificateIssuance
{
    public function __construct(
        private readonly CertificateService $certificates,
        private readonly CertificatePdfService $pdfs,
        private readonly InboxNotificationService $inbox,
        private readonly ProgramRegistrationService $registrations,
        private readonly EmailLogService $emailLogs,
    ) {}

    public function issueRegistration(int $registrationId, ?int $issuedById): void
    {
        $registration = ProgramRegistration::query()
            ->with(['user', 'trainingProgram'])
            ->find($registrationId);

        if ($registration === null || $registration->user === null || $registration->trainingProgram === null) {
            return;
        }

        if (! in_array($registration->status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)) {
            return;
        }

        if (! $this->backgroundFileExists($registration->trainingProgram)) {
            throw new RuntimeException('Program certificate background is missing.');
        }

        $issuer = $issuedById !== null ? User::query()->find($issuedById) : null;
        $certificate = $this->certificates->issue($registration->user, $registration->trainingProgram, $issuer);
        $certificate = $this->ensurePdf($certificate, $registration->user, $issuer);

        if ($registration->isApproved() && $issuer !== null) {
            $this->registrations->markCompleted(
                $registration,
                $issuer,
                $registration->score !== null ? (float) $registration->score : null,
                $registration->attendance_percentage !== null ? (float) $registration->attendance_percentage : null,
            );
        }

        $this->sendProgramMail($certificate, $issuer, force: false);
    }

    public function resend(Certificate $certificate, ?User $actor): void
    {
        $certificate->loadMissing('user');
        if ($certificate->user === null) {
            return;
        }

        $certificate = $this->ensurePdf($certificate, $certificate->user, $actor);
        $this->sendProgramMail($certificate, $actor, force: true);
    }

    private function ensurePdf(Certificate $certificate, User $recipient, ?User $actor): Certificate
    {
        if (! $this->pdfIsReady($certificate)) {
            $program = $certificate->certificateable;
            if (! $program instanceof TrainingProgram || ! $this->backgroundFileExists($program)) {
                throw new RuntimeException('Program certificate background is missing.');
            }

            $this->pdfs->regenerate($certificate);
            $certificate->refresh();
        }

        if (! $this->pdfIsReady($certificate)) {
            throw new RuntimeException('Program certificate PDF is missing.');
        }

        if (! $this->inboxAlreadySent($certificate)) {
            $this->inbox->certificateIssued($recipient, $certificate, $actor);
        }

        return $certificate;
    }

    private function sendProgramMail(Certificate $certificate, ?User $actor, bool $force): void
    {
        if (! $force && $certificate->emailed_at !== null) {
            return;
        }

        if (! $this->pdfIsReady($certificate)) {
            return;
        }

        $certificate->loadMissing(['user', 'certificateable']);
        $recipient = $certificate->user;
        $program = $certificate->certificateable;
        if ($recipient === null || ! $program instanceof TrainingProgram) {
            return;
        }

        $subject = 'تم إصدار شهادتك — '.$program->title;
        $this->emailLogs->send(
            recipient: $recipient,
            notification: new CertificateReadyEmail(
                certificate: $certificate,
                activityLabel: $program->title,
                subjectLine: $subject,
                introLine: 'صدرت شهادتك في «'.$program->title.'». يمكنك تنزيلها من البوابة أو من المرفق.',
                buttonLabel: 'عرض الشهادة',
                buttonUrl: route('portal.certificates'),
                attachPdf: true,
            ),
            templateKey: 'certificate.program_ready',
            subject: $subject,
            sentBy: $actor,
        );

        $log = EmailLog::query()
            ->where('recipient_email', $recipient->email)
            ->where('template_key', 'certificate.program_ready')
            ->latest('id')
            ->first();

        if ($log === null || $log->status !== 'sent') {
            throw new RuntimeException('Program certificate email was not sent.');
        }

        $certificate->forceFill(['emailed_at' => now()])->save();
    }

    private function backgroundFileExists(TrainingProgram $program): bool
    {
        $program->loadMissing('certificateTemplate');
        $relative = PublicDiskPath::normalize($program->certificateTemplate?->background_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return false;
        }

        return Storage::disk('public')->exists($relative);
    }

    private function pdfIsReady(Certificate $certificate): bool
    {
        $relative = PublicDiskPath::normalize($certificate->file_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return false;
        }

        return Storage::disk('public')->exists($relative);
    }

    private function inboxAlreadySent(Certificate $certificate): bool
    {
        return InboxNotification::query()
            ->where('user_id', $certificate->user_id)
            ->where('type', InboxNotificationType::CertificateIssued)
            ->where('context->resource', 'certificate')
            ->where('context->id', $certificate->getKey())
            ->exists();
    }
}
