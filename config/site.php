<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public site
    |--------------------------------------------------------------------------
    |
    | Copy shown on the marketing pages, plus the inbox that contact form
    | submissions are delivered to.
    |
    */

    'tagline' => env('SITE_TAGLINE', 'Money, tracked the way you actually think about it.'),

    'contact_mail_address' => env('SITE_CONTACT_MAIL', 'hello@example.com'),

    'legal' => [
        'company' => env('SITE_COMPANY', env('APP_NAME', 'MyMoney')),
        'updated_at' => env('SITE_LEGAL_UPDATED', '2026-08-01'),
    ],

];
