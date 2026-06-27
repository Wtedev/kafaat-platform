<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private documents disk
    |--------------------------------------------------------------------------
    |
    | Must point to durable private storage (not public disk). In production
    | use S3 or equivalent persistent object storage via PRIVATE_DOCUMENTS_DISK.
    |
    */
    'private_disk' => env('PRIVATE_DOCUMENTS_DISK', 'private_documents'),

    /*
    |--------------------------------------------------------------------------
    | Maximum CV upload size (kilobytes)
    |--------------------------------------------------------------------------
    */
    'max_size_kb' => (int) env('CV_MAX_SIZE_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types (PDF only — no malware scanner configured)
    |--------------------------------------------------------------------------
    */
    'allowed_mimes' => [
        'application/pdf',
    ],

    'allowed_extensions' => [
        'pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage path prefix inside the private disk
    |--------------------------------------------------------------------------
    */
    'storage_directory' => 'cv',

];
