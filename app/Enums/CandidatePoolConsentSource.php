<?php

namespace App\Enums;

enum CandidatePoolConsentSource: string
{
    case ProfilePopup = 'profile_popup';
    case PrivacySettings = 'privacy_settings';
    case CompetencyPage = 'competency_page';
}
