<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        private readonly CertificatePdfService $pdfService,
        private readonly InboxNotificationService $inboxNotifications,
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

        $certificate = Certificate::create([
            'user_id' => $user->id,
            'certificateable_type' => $certificateable->getMorphClass(),
            'certificateable_id' => $certificateable->getKey(),
            'certificate_number' => $this->generateCertificateNumber(),
            'verification_code' => $this->generateVerificationCode(),
            'issued_at' => now(),
        ]);

        // Generate PDF and attach path
        $filePath = $this->pdfService->generate($certificate);
        $certificate->update(['file_path' => $filePath]);

        $this->inboxNotifications->certificateIssued($user, $certificateable, $issuedBy);

        return $certificate->fresh();
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
}
