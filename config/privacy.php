<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Privacy policy acknowledgement checkbox text
    |--------------------------------------------------------------------------
    |
    | Shown beside the registration checkbox and stored in acknowledgement
    | records as a snapshot at the time of user action.
    |
    */
    'acknowledgement_checkbox_text' => 'أقر بأنني اطلعت على سياسة الخصوصية.',

    /*
    |--------------------------------------------------------------------------
    | Active policy cache key
    |--------------------------------------------------------------------------
    */
    'active_policy_cache_key' => 'privacy_policy.active_version',

    /*
    |--------------------------------------------------------------------------
    | Account deletion identity verification TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Re-verification must be recent before executing an approved deletion.
    |
    */
    'account_deletion' => [
        'identity_verification_ttl_seconds' => (int) env('PRIVACY_ACCOUNT_DELETION_IDENTITY_TTL', 900),
    ],

];
