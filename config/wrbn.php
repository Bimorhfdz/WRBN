<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dummy payments
    |--------------------------------------------------------------------------
    |
    | When the application runs in production with a dummy payment provider,
    | the process refuses to boot unless ALLOW_DUMMY_PAY=true is set
    | explicitly, and POST /api/payments/{id}/dummy-pay returns 403.
    |
    */

    'allow_dummy_pay' => (bool) env('ALLOW_DUMMY_PAY', false),

    'payment_provider' => env('PAYMENT_PROVIDER', 'dummy'),

    'payment_api_url' => env('PAYMENT_API_URL', ''),

    'payment_api_key' => env('PAYMENT_API_KEY', ''),

    'sheets_webhook_url' => env('GOOGLE_SHEETS_WEBHOOK_URL', ''),

    'max_file_size_mb' => (int) env('MAX_FILE_SIZE_MB', 10),

    /*
    |--------------------------------------------------------------------------
    | Admin session lifetime (minutes). Legacy token expiry: 8 hours.
    |--------------------------------------------------------------------------
    */

    'admin_session_minutes' => (int) env('ADMIN_SESSION_MINUTES', 480),

    /*
    |--------------------------------------------------------------------------
    | Admin bootstrap (php artisan wrbn:create-admin)
    |--------------------------------------------------------------------------
    */

    'admin_role' => env('ADMIN_ROLE', 'superadmin'),

    'admin_email' => env('ADMIN_EMAIL', ''),

    'admin_password' => env('ADMIN_PASSWORD', ''),

    'admin_reset_password' => strtolower((string) env('ADMIN_RESET_PASSWORD', 'false')) === 'true',

    /*
    |--------------------------------------------------------------------------
    | Member plan (second door)
    |--------------------------------------------------------------------------
    */

    'member_max_active' => (int) env('MEMBER_MAX_ACTIVE', 3),

    /*
    |--------------------------------------------------------------------------
    | General contact person + WhatsApp bot
    |--------------------------------------------------------------------------
    */

    'cp_general_name' => env('CP_GENERAL_NAME', ''),

    'cp_general_whatsapp' => env('CP_GENERAL_WHATSAPP', ''),

    'bot' => [
        'provider' => env('BOT_PROVIDER', 'link'),
        'api_url' => env('BOT_API_URL', ''),
        'api_key' => env('BOT_API_KEY', ''),
    ],

];
