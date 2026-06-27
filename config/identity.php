<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity lookup HMAC key
    |--------------------------------------------------------------------------
    |
    | Independent from APP_KEY. Used only for duplicate detection via
    | identity_number_lookup_hash. Must be set in production secrets.
    |
    */
    'lookup_key' => env('IDENTITY_LOOKUP_KEY'),

];
