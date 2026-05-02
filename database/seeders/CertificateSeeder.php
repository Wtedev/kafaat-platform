<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramRegistration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * شهادات للتسجيلات المكتملة في البرامج فقط، وفق شروط الأهلية (حضور ودرجة).
 */
class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $registrations = ProgramRegistration::query()
            ->where('status', RegistrationStatus::Completed)
            ->with(['trainingProgram', 'user'])
            ->get();

        $eligible = $registrations->filter(function (ProgramRegistration $registration): bool {
            if (! $registration->isEligibleForCertificate()) {
                return false;
            }

            return $registration->trainingProgram !== null;
        });

        if ($eligible->isEmpty()) {
            $this->command?->warn('CertificateSeeder: no completed program registrations eligible for a certificate. Skipping.');

            return;
        }

        foreach ($eligible as $registration) {
            $program = $registration->trainingProgram;
            $type = $program->getMorphClass();

            Certificate::firstOrCreate(
                [
                    'user_id' => $registration->user_id,
                    'certificateable_type' => $type,
                    'certificateable_id' => $program->getKey(),
                ],
                [
                    'certificate_number' => $this->uniqueCertificateNumber(),
                    'verification_code' => $this->uniqueVerificationCode(),
                    'file_path' => null,
                    'issued_at' => now()->subDays(rand(1, 12)),
                ]
            );
        }
    }

    private function uniqueCertificateNumber(): string
    {
        do {
            $number = 'CERT-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Certificate::where('certificate_number', $number)->exists());

        return $number;
    }

    private function uniqueVerificationCode(): string
    {
        do {
            $code = bin2hex(random_bytes(16));
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }
}
