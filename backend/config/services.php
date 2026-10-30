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

    // "Continue with Google": ONE platform-level OAuth client; empty client id = feature off (button hidden, routes 404).
    // Redirect URI registered once in Google: https://{APP_CENTRAL_DOMAIN}/auth/google/callback (see docs/PROGRESS.md).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // extra OAuth client ids accepted as the ID-token audience on POST api/v1/auth/google (Android / iOS clients), comma separated
        'mobile_client_ids' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_MOBILE_CLIENT_IDS', '')))),
    ],


    // Node WhatsApp gateway: infrastructure secrets only (URL + shared tokens). Business settings live in the database (auth_settings).
    'whatsapp' => [
        'url' => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3100'),
        'token' => env('WHATSAPP_SERVICE_TOKEN'),
        'callback_secret' => env('WHATSAPP_CALLBACK_SECRET'),
    ],

];
