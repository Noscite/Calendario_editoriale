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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Social Connections
    |--------------------------------------------------------------------------
    |
    | Credenziali per Facebook/Instagram (Meta), LinkedIn, Google Business.
    | Replica esatta di settings.py Python.
    |
    */

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'linkedin_business' => [
        'client_id' => env('LINKEDIN_BUSINESS_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_BUSINESS_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI APIs
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key'             => env('ANTHROPIC_API_KEY'),
        'requests_per_minute' => env('ANTHROPIC_REQUESTS_PER_MINUTE', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'perplexity' => [
        'api_key' => env('PERPLEXITY_API_KEY'),
    ],

    'playwright' => [
        'url'     => env('PLAYWRIGHT_SERVICE_URL', 'http://127.0.0.1:3099'),
        'timeout' => env('PLAYWRIGHT_TIMEOUT', 45),
        'enabled' => env('PLAYWRIGHT_ENABLED', true),
    ],

    'pagespeed' => [
        'api_key' => env('GOOGLE_PAGESPEED_API_KEY', ''),  // opzionale: aumenta rate limit
        'enabled' => env('PAGESPEED_ENABLED', true),
    ],

    'ssllabs' => [
        'enabled' => env('SSLLABS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | URL del frontend per i redirect OAuth.
    | In Python: settings.FRONTEND_URL = "https://calendar.noscite.it"
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'https://calendar.noscite.it'),

    /*
    |--------------------------------------------------------------------------
    | Azure AD (Microsoft Entra ID)
    |--------------------------------------------------------------------------
    */

    'azure' => [
        'client_id'     => env('AZURE_AD_CLIENT_ID'),
        'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
        'tenant_id'     => env('AZURE_AD_TENANT_ID'),
        'redirect_uri'  => env('AZURE_AD_REDIRECT_URI'),
    ],

];
