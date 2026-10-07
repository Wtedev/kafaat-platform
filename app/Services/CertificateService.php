<?php

namespace App\Services;

use App\Enums\CertificatePdfStatus;
use App\Models\Certificate;
use App\Models\EmailLog;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Notifications\CertificateReadyEmail;

class CertificateService
{
    public function __construct(
        private readonly EmailLogService $emailLogService,
    ) {}

    public function emailCertificate(Certificate $certificate, ?User $sentBy = null): bool
    {
        $certificate->loadMissing(['user', 'certificateable']);

        if ($certificate->user === null || $certificate->isRevoked() || $certificate->emailed_at !== null) {
            return false;
        }

        if ($certificate->pdf_status !== CertificatePdfStatus::Generated && blank($certificate->file_path)) {
            return false;
        }

        $snapshot = is_array($certificate->data_snapshot) ? $certificate->data_snapshot : [];
        $snapshotTitle = $snapshot['activity_title'] ?? null;
        $label = is_string($snapshotTitle) && $snapshotTitle !== ''
            ? $snapshotTitle
            : match (true) {
                $certificate->certificateable instanceof TrainingProgram => $certificate->certificateable->title,
                $certificate->certificateable instanceof LearningPath => $certificate->certificateable->title,
                $certificate->certificateable instanceof VolunteerOpportunity => $certificate->certificateable->title,
                default => 'النشاط',
            };

        $this->emailLogService->send(
            recipient: $certificate->user,
            notification: new CertificateReadyEmail($certificate, $label),
            templateKey: 'certificate.ready',
            subject: 'شهادتك جاهزة — '.$label,
            sentBy: $sentBy,
        );

        $sent = EmailLog::query()
            ->where('recipient_email', $certificate->user->email)
            ->where('template_key', 'certificate.ready')
            ->latest('id')
            ->value('status') === 'sent';

        if (! $sent) {
            return false;
        }

        $certificate->update(['emailed_at' => now()]);

        return true;
    }
}
