<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\CertificateReadyEmail;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        private readonly CertificatePdfService $pdfService,
        private readonly InboxNotificationService $inboxNotifications,
        private readonly EmailLogService $emailLogService,
    ) {}

    /**
     * Issue a certificate for a user against a given certificateable entity
     * (e.g. a TrainingProgram or LearningPath).
     * Idempotent: returns the existing certificate if one already exists.
     */
    public function issue(User $user, Model $certificateable, ?User $issuedBy = null): Certificate
    {
        // Return existing certificate if already issued
        $existing = Certificate::query()
            ->where('user_id', $user->id)
            ->where('certificateable_type', $certificateable->getMorphClass())
            ->where('certificateable_id', $certificateable->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $certificate = Certificate::create([
                'user_id' => $user->id,
                'certificateable_type' => $certificateable->getMorphClass(),
                'certificateable_id' => $certificateable->getKey(),
                'certificate_number' => $this->generateCertificateNumber(),
                'verification_code' => $this->generateVerificationCode(),
                'issued_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $winner = Certificate::query()
                ->where('user_id', $user->id)
                ->where('certificateable_type', $certificateable->getMorphClass())
                ->where('certificateable_id', $certificateable->getKey())
                ->first();

            if ($winner === null) {
                throw $exception;
            }

            return $winner;
        }

        // Generate PDF and attach path
        $filePath = $this->pdfService->generate($certificate);
        $certificate->update(['file_path' => $filePath]);

        $this->inboxNotifications->certificateIssued($user, $certificate, $issuedBy);

        return $certificate->fresh();
    }

    /**
     * @deprecated Program certificates are issued manually from the certificates tab.
     *             Learning paths still use {@see issueForPathRegistration()}.
     */
    public function issueForProgramRegistration(ProgramRegistration $registration, ?User $issuedBy = null): ?Certificate
    {
        $registration->loadMissing(['user', 'trainingProgram']);

        if (! $registration->isEligibleForCertificate()) {
            return null;
        }

        if ($registration->isApproved()) {
            $registration->update(['status' => RegistrationStatus::Completed]);
            $registration->refresh();
        }

        return $this->issue($registration->user, $registration->trainingProgram, $issuedBy);
    }

    public function issueForPathRegistration(PathRegistration $registration, ?User $issuedBy = null): ?Certificate
    {
        $registration->loadMissing(['user', 'learningPath']);

        if (! $registration->isEligibleForCertificate()) {
            return null;
        }

        if ($registration->isApproved()) {
            $registration->update([
                'status' => RegistrationStatus::Completed,
                'completed_at' => now(),
            ]);
            $registration->refresh();
        }

        return $this->issue($registration->user, $registration->learningPath, $issuedBy);
    }

    /**
     * @deprecated Program certificates are queued from the certificates tab.
     *             This still applies the old 75% rule and is no longer called by the tab.
     */
    public function issueEligibleProgramRegistrations(TrainingProgram $program, ?User $issuedBy = null): int
    {
        $count = 0;

        $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->with(['user', 'trainingProgram'])
            ->each(function (ProgramRegistration $registration) use ($issuedBy, &$count): void {
                if ($this->issueForProgramRegistration($registration, $issuedBy) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    public function issueEligiblePathRegistrations(LearningPath $path, ?User $issuedBy = null): int
    {
        $count = 0;

        $path->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->with(['user', 'learningPath'])
            ->each(function (PathRegistration $registration) use ($issuedBy, &$count): void {
                if ($this->issueForPathRegistration($registration, $issuedBy) !== null) {
                    $count++;
                }
            });

        return $count;
    }

    public function emailCertificate(Certificate $certificate, ?User $sentBy = null): bool
    {
        $certificate->loadMissing(['user', 'certificateable']);

        if ($certificate->user === null) {
            return false;
        }

        $label = match (true) {
            $certificate->certificateable instanceof TrainingProgram => $certificate->certificateable->title,
            $certificate->certificateable instanceof LearningPath => $certificate->certificateable->title,
            default => 'نشاطك',
        };

        $this->emailLogService->send(
            recipient: $certificate->user,
            notification: new CertificateReadyEmail($certificate, $label),
            templateKey: 'certificate.ready',
            subject: 'شهادتك جاهزة — '.$label,
            sentBy: $sentBy,
        );

        return true;
    }

    /**
     * @deprecated Program certificate email is sent by {@see IssueProgramCertificateJob}.
     *             Learning paths still use {@see emailEligiblePathCertificates()}.
     */
    public function emailEligibleProgramCertificates(TrainingProgram $program, ?User $sentBy = null): int
    {
        $count = 0;

        $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->with('user')
            ->each(function (ProgramRegistration $registration) use ($sentBy, &$count): void {
                if (! $registration->isEligibleForCertificate()) {
                    return;
                }

                $certificate = $registration->certificateForEntity()
                    ?? $this->issueForProgramRegistration($registration, $sentBy);

                if ($certificate !== null && $this->emailCertificate($certificate, $sentBy)) {
                    $count++;
                }
            });

        return $count;
    }

    public function emailEligiblePathCertificates(LearningPath $path, ?User $sentBy = null): int
    {
        $count = 0;

        $path->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->with('user')
            ->each(function (PathRegistration $registration) use ($sentBy, &$count): void {
                if (! $registration->isEligibleForCertificate()) {
                    return;
                }

                $certificate = $registration->certificateForEntity()
                    ?? $this->issueForPathRegistration($registration, $sentBy);

                if ($certificate !== null && $this->emailCertificate($certificate, $sentBy)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Generate a unique, human-readable certificate number.
     * Format: CERT-YYYYMMDD-XXXXXXXX
     */
    private function generateCertificateNumber(): string
    {
        do {
            $number = 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a cryptographically random verification code (32 hex chars).
     */
    private function generateVerificationCode(): string
    {
        do {
            $code = bin2hex(random_bytes(16));
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
