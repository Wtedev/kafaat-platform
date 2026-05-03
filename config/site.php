<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public site contact & presence
    |--------------------------------------------------------------------------
    |
    | Shown on the public footer and other marketing surfaces. Override via .env when needed.
    |
    */

    'contact_email' => env('SITE_CONTACT_EMAIL', 'info@kafaat.org.sa'),

    /** National mobile, digits only (e.g. 05xxxxxxxx) */
    'contact_phone_local' => env('SITE_CONTACT_PHONE_LOCAL', '0537527747'),

    /** Shown in the footer (may include spaces) */
    'contact_phone_display' => env('SITE_CONTACT_PHONE_DISPLAY', '053 752 7747'),

    /** E.164 country + number without leading + (e.g. 9665xxxxxxxx) */
    'contact_phone_e164' => env('SITE_CONTACT_PHONE_E164', '966537527747'),

    'social' => [
        [
            'key' => 'youtube',
            'label' => 'يوتيوب',
            'url' => 'https://youtube.com/@kafaatbyc?si=Vxhs5X3vQhSFVV01',
        ],
        [
            'key' => 'linkedin',
            'label' => 'لينكدإن',
            'url' => 'https://www.linkedin.com/company/جمعية-كفاءات-لـبناء-قدرات-الشباب/',
        ],
        [
            'key' => 'x',
            'label' => 'إكس',
            'url' => 'https://x.com/KafaatBYC',
        ],
        [
            'key' => 'tiktok',
            'label' => 'تيك توك',
            'url' => 'https://www.tiktok.com/@kafaatbyc?_t=ZS-8yURjlh4To8&_r=1',
        ],
        [
            'key' => 'instagram',
            'label' => 'إنستغرام',
            'url' => 'https://www.instagram.com/kafaatbyc?igsh=MXc4MzhiNTJ6M2FtMA==',
        ],
    ],

    /** Ordered lines for display (RTL-friendly) */
    'address_lines' => [
        'بريدة — حي المنتزة الغربي',
        'طريق الملك عبدالعزيز',
        'الرمز البريدي 52374',
        'المملكة العربية السعودية',
    ],

    /**
     * Footer map: coordinates + Google Maps link for directions.
     * The public footer renders an interactive map (Leaflet + CARTO dark_matter tiles).
     */
    'maps' => [
        'link' => env('SITE_MAPS_URL', 'https://maps.app.goo.gl/9Kpm1jhGkCMKKq4p6'),
        'lat' => (float) env('SITE_MAPS_LAT', '26.3676773'),
        'lng' => (float) env('SITE_MAPS_LNG', '43.9288304'),
        'zoom' => (int) env('SITE_MAPS_ZOOM', '16'),
    ],

    'working_hours' => [
        'title' => 'ساعات العمل',
        'days' => 'الأحد — الخميس',
        'shifts' => [
            [
                'title' => 'الإدارة النسائية',
                'hours' => '8 صباحًا — 2 مساءً',
            ],
            [
                'title' => 'الإدارة الرجالية',
                'hours' => '12 ظهرًا — 7 مساءً',
            ],
        ],
    ],

];
