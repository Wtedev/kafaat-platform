<?php

namespace App\Data\Certificates;

use App\Enums\CertificateEligibilityStatus;

final readonly class EligibilityResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public bool $eligible,
        public CertificateEligibilityStatus $status,
        public array $reasons,
    ) {}

    public static function eligible(): self
    {
        return new self(true, CertificateEligibilityStatus::Eligible, []);
    }

    /**
     * @param  list<string>  $reasons
     */
    public static function notEligible(array $reasons): self
    {
        return new self(false, CertificateEligibilityStatus::NotEligible, $reasons);
    }

    /**
     * @param  list<string>  $reasons
     */
    public static function awaitingData(array $reasons): self
    {
        return new self(false, CertificateEligibilityStatus::AwaitingData, $reasons);
    }

    public static function notConfigured(): self
    {
        return new self(false, CertificateEligibilityStatus::NotConfigured, [
            'لم يُضبط قالب الشهادة بعد',
        ]);
    }

    public function label(): string
    {
        return match ($this->status) {
            CertificateEligibilityStatus::Eligible => 'مؤهل',
            CertificateEligibilityStatus::NotEligible => 'غير مؤهل',
            CertificateEligibilityStatus::AwaitingData => 'بانتظار البيانات',
            CertificateEligibilityStatus::NotConfigured => 'لم يُضبط قالب الشهادة بعد',
        };
    }

    public function color(): string
    {
        return match ($this->status) {
            CertificateEligibilityStatus::Eligible => 'success',
            CertificateEligibilityStatus::NotEligible => 'danger',
            CertificateEligibilityStatus::AwaitingData => 'warning',
            CertificateEligibilityStatus::NotConfigured => 'gray',
        };
    }
}
