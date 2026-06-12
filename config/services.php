<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai' => [
        'logging' => env('AI_LOGGING', false),
        'debug' => env('AI_DEBUG', false),
        'alert_enabled' => env('AI_ALERT_ENABLED', true),
        'alert_throttle_minutes' => (int) env('AI_ALERT_THROTTLE_MINUTES', 60),
        'health_check_timeout' => (int) env('AI_HEALTH_CHECK_TIMEOUT', 10),
    ],

    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:7b-instruct-q4_K_M'),
    ],

    'tubus' => [
        'token' => env('TUBUS_TOKEN', null),
    ],

    /*
    | MadelineProto (ReadTelegramChats): повторы при Amp\CancelledException на getHistory.
    | См. MPROTO_* в .env и docs/madelineproto-vpn-routing.md
    */
    'madeline_proto' => [
        'get_history_max_attempts' => (int) env('MPROTO_GETHISTORY_MAX_ATTEMPTS', 3),
    ],
];
