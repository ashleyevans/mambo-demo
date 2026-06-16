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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'truelayer' => [
        'client_id' => env('TRUELAYER_CLIENT_ID'),
        'client_secret' => env('TRUELAYER_CLIENT_SECRET'),
        'redirect' => env('TRUELAYER_REDIRECT_URI'),
        'environment' => env('TRUELAYER_ENVIRONMENT', 'sandbox'),
        'scopes' => env('TRUELAYER_SCOPES', 'info accounts balance transactions offline_access'),
        'providers' => env('TRUELAYER_PROVIDERS', 'uk-cs-mock uk-ob-all uk-oauth-all'),
        'consent_days' => (int) env('TRUELAYER_CONSENT_DAYS', 90),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
        // The decision of who to notify is made in-app: only this user's
        // transactions trigger a push. OneSignal then broadcasts to the app's
        // subscribers (a single-user demo project), so no per-user targeting.
        'notify_email' => env('ONESIGNAL_NOTIFY_EMAIL', 'ash@cellcastonline.com'),
        'segment' => env('ONESIGNAL_SEGMENT', 'Subscribed Users'),
        // Best-effort merchant logo for push images. TrueLayer provides no logo,
        // so this is resolved from the merchant name. Swap for an enrichment
        // provider's endpoint later. Supports {domain} and {merchant} placeholders.
        'merchant_logo_template' => env('ONESIGNAL_MERCHANT_LOGO_TEMPLATE', 'https://www.google.com/s2/favicons?domain={domain}&sz=128'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
