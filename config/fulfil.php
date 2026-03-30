<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Fulfil Environment
    |--------------------------------------------------------------------------
    |
    | This option controls which Fulfil environment to use by default.
    | Supported: "sandbox", "production"
    |
    */
    'default' => env('FULFIL_DEFAULT_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Fulfil Environments
    |--------------------------------------------------------------------------
    |
    | Configuration for each Fulfil environment.
    |
    */
    'environments' => [
        'sandbox' => [
            'subdomain' => env('FULFIL_SANDBOX_SUBDOMAIN'),
            'token' => env('FULFIL_SANDBOX_TOKEN'),
        ],
        'production' => [
            'subdomain' => env('FULFIL_PRODUCTION_SUBDOMAIN'),
            'token' => env('FULFIL_PRODUCTION_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache TTL in seconds for Fulfil API responses.
    |
    */
    'cache' => [
        'ttl' => env('FULFIL_CACHE_TTL', 3600), // 1 hour default
        'prefix' => 'fulfil_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting / Retry Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for handling API rate limits (429 errors).
    | Aligned with PIM's retry approach (3 attempts) but with shorter
    | backoff delays appropriate for synchronous HTTP requests.
    |
    | PIM uses queue job retries: tries=3, backoff=[15, 30, 120] seconds
    | Sales uses HTTP retries: tries=3, backoff=[2, 5, 10] seconds
    |
    */
    'rate_limit' => [
        'max_retries' => env('FULFIL_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configuration
    |--------------------------------------------------------------------------
    |
    | B2B/Retail channel settings for filtering orders.
    |
    */
    'channels' => [
        'retail' => [
            'id' => 19,
            'code' => 'RTL1',
            'name' => 'Retail Channel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Configuration
    |--------------------------------------------------------------------------
    |
    | Account IDs for filtering invoices.
    |
    */
    'accounts' => [
        'ar_b2b' => 77, // Accounts Receivable - B2B (same ID in sandbox and production)
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Attribute IDs
    |--------------------------------------------------------------------------
    |
    | Known attribute IDs for product filtering and data extraction.
    |
    */
    'attributes' => [
        'class' => 7,
        'start_date' => 8,
        'end_date' => 10,       // Discontinued On
        'season' => 47,
        'sales_channel' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Metafield IDs
    |--------------------------------------------------------------------------
    |
    | Metafield IDs for custom fields on Contact (party.party) records.
    | These IDs differ between sandbox and production environments.
    | Use `php artisan fulfil:discover-metafields` to discover IDs.
    |
    | Metafields still stored in Fulfil:
    | - invoice_discount: Float - Discounts applied at invoice level
    | - shelf_life: Integer - Shelf life required on arrival (days)
    |
    | The following fields have been migrated to local DB (local_customer_metadata):
    | edi, consolidated_invoicing, requires_customer_skus, broker, broker_commission
    |
    */
    'metafields' => [
        'sandbox' => [
            'invoice_discount' => env('FULFIL_SANDBOX_METAFIELD_INVOICE_DISCOUNT'),
            'shelf_life' => env('FULFIL_SANDBOX_METAFIELD_SHELF_LIFE'),
        ],
        'production' => [
            'invoice_discount' => env('FULFIL_PRODUCTION_METAFIELD_INVOICE_DISCOUNT'),
            'shelf_life' => env('FULFIL_PRODUCTION_METAFIELD_SHELF_LIFE'),
        ],
    ],
];
