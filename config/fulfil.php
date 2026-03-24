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
    | AR Automation metafields:
    | - edi: Boolean - Do we communicate with customer via EDI?
    | - consolidated_invoicing: Boolean - Consolidate same-day shipments into one invoice
    | - requires_customer_skus: Boolean - Customer requires their SKUs on invoices
    | - invoice_discount: Float - Discounts applied at invoice level
    |
    | Customer detail metafields:
    | - shelf_life: Integer - Shelf life required on arrival (days)
    | - broker: Boolean - Does customer use a broker?
    | - broker_commission: Float - Broker commission percentage
    |
    */
    'metafields' => [
        'sandbox' => [
            // AR Automation
            'edi' => env('FULFIL_SANDBOX_METAFIELD_EDI'),
            'consolidated_invoicing' => env('FULFIL_SANDBOX_METAFIELD_CONSOLIDATED_INVOICING'),
            'requires_customer_skus' => env('FULFIL_SANDBOX_METAFIELD_REQUIRES_CUSTOMER_SKUS'),
            'invoice_discount' => env('FULFIL_SANDBOX_METAFIELD_INVOICE_DISCOUNT'),
            // Customer details
            'shelf_life' => env('FULFIL_SANDBOX_METAFIELD_SHELF_LIFE'),
            'broker' => env('FULFIL_SANDBOX_METAFIELD_BROKER'),
            'broker_commission' => env('FULFIL_SANDBOX_METAFIELD_BROKER_COMMISSION'),
        ],
        'production' => [
            // AR Automation
            'edi' => env('FULFIL_PRODUCTION_METAFIELD_EDI'),
            'consolidated_invoicing' => env('FULFIL_PRODUCTION_METAFIELD_CONSOLIDATED_INVOICING'),
            'requires_customer_skus' => env('FULFIL_PRODUCTION_METAFIELD_REQUIRES_CUSTOMER_SKUS'),
            'invoice_discount' => env('FULFIL_PRODUCTION_METAFIELD_INVOICE_DISCOUNT'),
            // Customer details
            'shelf_life' => env('FULFIL_PRODUCTION_METAFIELD_SHELF_LIFE'),
            'broker' => env('FULFIL_PRODUCTION_METAFIELD_BROKER'),
            'broker_commission' => env('FULFIL_PRODUCTION_METAFIELD_BROKER_COMMISSION'),
        ],
    ],
];
