<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_API_KEY'),
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

    'paypal' => [
        'mode'          => env('PAYPAL_MODE', 'mock'),
        'base_url'      => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    ],

    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

];