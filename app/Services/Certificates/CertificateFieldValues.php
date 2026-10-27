<?php

namespace App\Services\Certificates;

use App\Enums\CertificateFieldKey;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Support\Format\LocaleFormat;
use Illuminate\Support\Carbon;

final class CertificateFieldValues
{
    // A long four-part name, so the designer shows how real names fit the width.
    public const SAMPLE_NAME = 'عبدالعزيز بن محمد بن عبدالله السالم';

    public const SAMPLE_NUMBER = 'CERT-SAMPLE-00000000';

    public const SAMPLE_CODE = 'preview';

    /**
     * Values printed on an issued certificate. The QR value is the public verification URL.
     *
     * @return array<string, string>
     */
    public static function forCertificate(Certificate $certificate): array
    {
        $certificate->loadMissing(['user', 'certificateable']);
        $program = $certificate->certificateable instanceof TrainingProgram
            ? $certificate->certificateable
            : null;
        $program?->loadMissing('certificateTemplate');

        return self::pack(
            name: $certificate->user?->certificateName() ?? '',
            title: (string) ($certificate->certificateable->title ?? ''),
            issuedAt: $certificate->issued_at,
            startsAt: $program?->start_date,
            endsAt: $program?->end_date,
            hours: $program?->certificateTemplate?->hours,
            number: (string) $certificate->certificate_number,
            verificationCode: (string) $certificate->verification_code,
        );
    }

    /**
     * Sample values for the designer canvas and the PDF preview. No certificate row is involved.
     *
     * @return array<string, string>
     */
    public static function forPreview(TrainingProgram $program): array
    {
        $program->loadMissing('certificateTemplate');

        return self::pack(
            name: self::SAMPLE_NAME,
            title: (string) $program->title,
            issuedAt: Carbon::now(config('app.timezone')),
            startsAt: $program->start_date,
            endsAt: $program->end_date,
            hours: $program->certificateTemplate?->hours,
            number: self::SAMPLE_NUMBER,
            verificationCode: self::SAMPLE_CODE,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function pack(
        string $name,
        string $title,
        mixed $issuedAt,
        mixed $startsAt,
        mixed $endsAt,
        mixed $hours,
        string $number,
        string $verificationCode,
    ): array {
        return [
            CertificateFieldKey::BeneficiaryName->value => $name,
            CertificateFieldKey::ProgramTitle->value => $title,
            CertificateFieldKey::IssueDate->value => self::date($issuedAt),
            CertificateFieldKey::StartDate->value => self::date($startsAt),
            CertificateFieldKey::EndDate->value => self::date($endsAt),
            CertificateFieldKey::Hours->value => self::hours($hours),
            CertificateFieldKey::CertificateNumber->value => $number,
            CertificateFieldKey::VerificationQr->value => route('certificates.verify', $verificationCode),
        ];
    }

    private static function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return LocaleFormat::toLatinDigits($date->timezone(config('app.timezone'))->format('Y/m/d'));
    }

    private static function hours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // 12.00 prints as 12 and 12.50 as 12.5, the way people write hours.
        $formatted = LocaleFormat::number($value, 2, '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
