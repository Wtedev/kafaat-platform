<?php

namespace App\Enums;

enum CertificateEligibilityStatus: string
{
    case Eligible = 'eligible';
    case NotEligible = 'not_eligible';
    case AwaitingData = 'awaiting_data';
    case NotConfigured = 'not_configured';
}
