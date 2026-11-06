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

    /*
    |--------------------------------------------------------------------------
    | Currency Exchange Rates
    |--------------------------------------------------------------------------
    | Default provider is free (open.er-api.com) and updates daily. Set a key
    | if you switch to a provider that requires one.
    */

    'fx' => [
        'url' => env('FX_RATES_URL', 'https://open.er-api.com/v6/latest'),
        'key' => env('FX_RATES_KEY'),
        'key_param' => env('FX_RATES_KEY_PARAM', 'access_key'),
        'timeout' => env('FX_RATES_TIMEOUT', 10),
        // Path CA bundle opsional; dipakai bila file ada (mengatasi Windows/
        // PHP CLI tanpa cacert). Jika tidak ada, verifikasi sistem dipakai.
        'ca_bundle' => env('FX_RATES_CA_BUNDLE', storage_path('certs/cacert.pem')),
        'verify' => env('FX_RATES_VERIFY', true),
    ],

];
