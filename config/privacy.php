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

    /*
    |--------------------------------------------------------------------------
    | Personal data export (ZIP)
    |--------------------------------------------------------------------------
    */
    'export' => [
        'ttl_days' => max(1, (int) env('PRIVACY_EXPORT_TTL_DAYS', 7)),
        'disk' => env('PRIVATE_DOCUMENTS_DISK', 'private_documents'),
        'schema_version' => 1,
        'job_timeout_seconds' => 600,
        'job_tries' => 3,
        'job_backoff_seconds' => [60, 300, 900],
    ],

];
