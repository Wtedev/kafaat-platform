<?php

namespace App\Enums;

enum RetentionExceptionReasonCode: string
{
    case ActiveDispute = 'active_dispute';
    case RegulatoryRequirement = 'regulatory_requirement';
    case SecurityInvestigation = 'security_investigation';
    case FinancialRecord = 'financial_record';
    case CertificateVerification = 'certificate_verification';
    case ManagementHold = 'management_hold';

    public function label(): string
    {
        return match ($this) {
            self::ActiveDispute => 'نزاع قائم',
            self::RegulatoryRequirement => 'متطلب تنظيمي',
            self::SecurityInvestigation => 'تحقيق أمني',
            self::FinancialRecord => 'سجل مالي',
            self::CertificateVerification => 'تحقق شهادة',
            self::ManagementHold => 'تعليق إداري',
        };
    }
}
