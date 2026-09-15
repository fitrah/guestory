<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('EMAIL_FROM', 'Guestory <noreply@notify.proyek.org>'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wapi' => [
        'base_url' => env('WAPI_BASE_URL', 'https://wapi.proyek.org'),
        'api_key' => env('WAPI_API_KEY'),
        'number_id' => env('WAPI_NUMBER_ID'),
        'timeout' => (int) env('WAPI_TIMEOUT', 10),
        'dry_run' => (bool) env('WAPI_DRY_RUN', true),
    ],

];
