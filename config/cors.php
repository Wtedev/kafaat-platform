<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | This application is a same-origin Blade / Livewire / Filament monolith.
    | Paths remain limited to API-style routes so browsers do not receive a
    | permissive Access-Control-Allow-Origin: * for credentialed traffic.
    | When adding a cross-origin SPA or mobile client, set allowed_origins to
    | explicit HTTPS origins and enable supports_credentials only if required.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))) ?: [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-XSRF-TOKEN', 'X-Request-ID'],

    'exposed_headers' => ['X-Request-ID'],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
