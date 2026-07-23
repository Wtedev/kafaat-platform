<?php

return [

    'force_https' => env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs/CIDRs, or "*" to trust all (Railway TLS edge).
    | "*" is only safe while the PHP process is unreachable except via the
    | platform reverse proxy. Prefer explicit CIDRs when self-hosting.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_HOSTS', '')),
    ))),

    'headers' => [
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ),
        // Same-origin defaults; safe for Blade/Livewire/Filament monolith.
        'cross_origin_opener_policy' => env('SECURITY_COOP', 'same-origin'),
        'cross_origin_resource_policy' => env('SECURITY_CORP', 'same-origin'),
        'content_security_policy' => env('SECURITY_CSP', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: blob: https://*.basemaps.cartocdn.com https://*.tile.openstreetmap.org",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline' https://unpkg.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com",
            "connect-src 'self' https://*.basemaps.cartocdn.com",
        ])),
        'content_security_policy_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
    ],

    'health' => [
        'failed_jobs_warning_threshold' => (int) env('HEALTH_FAILED_JOBS_WARNING', 10),
        'scheduler_stale_hours' => (int) env('HEALTH_SCHEDULER_STALE_HOURS', 48),
        'queue_stale_minutes' => (int) env('HEALTH_QUEUE_STALE_MINUTES', 120),
    ],

    'production' => [
        'required_env' => [
            'APP_KEY',
            'APP_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'PRIVATE_DOCUMENTS_DISK',
            'IDENTITY_LOOKUP_KEY',
        ],
        'forbidden_queue_connections' => ['sync'],
        'forbidden_session_drivers' => ['array'],
        'forbidden_cache_stores' => ['file', 'array', 'null'],
        'forbidden_filesystem_defaults' => ['public'],
        'require_trusted_hosts' => (bool) env('SECURITY_REQUIRE_TRUSTED_HOSTS', true),
        'require_session_encrypt' => (bool) env('SECURITY_REQUIRE_SESSION_ENCRYPT', true),
        'require_stderr_logging' => (bool) env('SECURITY_REQUIRE_STDERR_LOGGING', true),
    ],

];
