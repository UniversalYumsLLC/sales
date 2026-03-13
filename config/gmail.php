<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gmail OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | These settings configure the Gmail OAuth integration for salespersons.
    | The client ID and secret can be the same as the main Google OAuth,
    | but the OAuth consent screen must include Gmail scopes.
    |
    */

    'client_id' => env('GMAIL_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
    'client_secret' => env('GMAIL_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
    'redirect_uri' => env('GMAIL_REDIRECT_URI', env('APP_URL').'/gmail/callback'),

    /*
    |--------------------------------------------------------------------------
    | OAuth Scopes
    |--------------------------------------------------------------------------
    |
    | The Gmail API scopes required for the integration.
    | - gmail.readonly: Read-only access to email messages and settings
    | - userinfo.email: Get the user's email address
    |
    */

    'scopes' => [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the email synchronization process.
    |
    */

    // How often to sync emails (in minutes)
    'sync_interval' => (int) env('GMAIL_SYNC_INTERVAL', 15),

    // Overlap time to prevent gaps (in minutes)
    // Emails are fetched from (last_sync - overlap) to (now)
    'sync_overlap' => (int) env('GMAIL_SYNC_OVERLAP', 2),

    // Maximum number of emails to fetch per sync
    'max_results_per_sync' => 100,

    // How far back to look on initial sync (in days)
    'initial_sync_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */

    'oauth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'api_base' => 'https://gmail.googleapis.com/gmail/v1',
];
