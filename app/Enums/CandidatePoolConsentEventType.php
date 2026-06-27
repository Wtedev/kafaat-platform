<?php

namespace App\Enums;

enum CandidatePoolConsentEventType: string
{
    case Prompted = 'prompted';
    case Granted = 'granted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Regranted = 'regranted';
}
