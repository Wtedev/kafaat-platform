<?php

namespace App\Support\Surveys;

use App\Services\Identity\IdentityNumberService;

final class SurveyNationalIdHasher
{
    /**
     * HMAC for access attempts. Distinct from identity_number_lookup_hash so the
     * attempt row cannot be joined back to a user.
     */
    public static function hash(string $material): string
    {
        return hash_hmac('sha256', 'survey-access|v1|'.$material, IdentityNumberService::lookupKey());
    }
}
