<?php

namespace App\Enums;

enum RetentionExceptionReasonCode: string
{
    case ActiveDispute = 'active_dispute';
    case RegulatoryRequirement = 'regulatory_requirement';
    case SecurityInvestigation = 'security_investigation';
    case FinancialRecord = 'financial_record';
    case CertificateVerification = 'certificate_verification';
}
