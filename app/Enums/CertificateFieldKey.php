<?php

namespace App\Enums;

enum CertificateFieldKey: string
{
    case BeneficiaryName = 'beneficiary_name';
    case ProgramTitle = 'program_title';
    case IssueDate = 'issue_date';
    case StartDate = 'start_date';
    case EndDate = 'end_date';
    case Hours = 'hours';
    case CertificateNumber = 'certificate_number';
    case VerificationQr = 'verification_qr';

    public function label(): string
    {
        return match ($this) {
            self::BeneficiaryName => 'اسم المستفيد',
            self::ProgramTitle => 'اسم البرنامج',
            self::IssueDate => 'تاريخ الإصدار',
            self::StartDate => 'تاريخ البداية',
            self::EndDate => 'تاريخ النهاية',
            self::Hours => 'عدد الساعات',
            self::CertificateNumber => 'رقم الشهادة',
            self::VerificationQr => 'رمز التحقق',
        };
    }

    public function isQr(): bool
    {
        return $this === self::VerificationQr;
    }
}
