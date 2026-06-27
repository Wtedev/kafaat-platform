<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sensitive access re-verification TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | After password confirmation, identity reveal and similar operations
    | trust the session for this duration only.
    |
    */
    'sensitive_reverify_ttl_seconds' => (int) env('ACCESS_CONTROL_SENSITIVE_REVERIFY_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxy request ID header
    |--------------------------------------------------------------------------
    |
    | Only honored when the request is from a trusted proxy (see TrustProxies).
    | Never accept arbitrary client-supplied IDs in production without this gate.
    |
    */
    'trusted_request_id_header' => env('ACCESS_CONTROL_TRUSTED_REQUEST_ID_HEADER', 'X-Request-ID'),

];
