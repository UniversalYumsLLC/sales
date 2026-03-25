<?php

namespace App\Services;

use App\Exceptions\FulfilUnavailableException;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FulfilService
{
    protected string $baseUrl;

    protected string $token;

    protected int $cacheTtl;

    protected string $cachePrefix;

    protected int $maxRetries;

    protected string $environment;

    public function __construct(?string $environment = null)
    {
        // If no environment specified, check Test Mode to determine which to use
        if ($environment === null) {
            $testMode = app(TestModeService::class);
            $env = $testMode->getFulfilEnvironment();
        } else {
            $env = $environment;
        }

        // Fall back to config default if still not set
        $env = $env ?? config('fulfil.default');
        $this->environment = $env;
        $config = config("fulfil.environments.{$env}");

        if (! $config['subdomain'] || ! $config['token']) {
            throw new \RuntimeException("Fulfil {$env} environment not configured");
        }

        $this->baseUrl = "https://{$config['subdomain']}.fulfil.io/api/v2";
        $this->token = $config['token'];
        $this->cacheTtl = config('fulfil.cache.ttl', 3600);
        $this->cachePrefix = config('fulfil.cache.prefix', 'fulfil_').$this->environment.'_';
        $this->maxRetries = config('fulfil.rate_limit.max_retries', 3);

        Log::debug('FulfilService initialized', [
            'environment' => $env,
            'subdomain' => $config['subdomain'],
        ]);
    }

    /**
     * Get the current Fulfil environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Make an API request to Fulfil with retry logic for rate limiting.
     *
     * Note: Unlike the PIM which uses queue job retries with long backoff times
     * (15s, 30s, 120s), this service handles synchronous requests where users
     * are waiting. We use HTTP-level retries with shorter delays to balance
     * responsiveness with rate limit compliance.
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = "{$this->baseUrl}/{$endpoint}";
        $lastException = null;
        $startTime = microtime(true);

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$this->token,
                        'Content-Type' => 'application/json',
                    ])->{$method}($url, $options['json'] ?? $options['query'] ?? []);
            } catch (ConnectionException $e) {
                $duration = microtime(true) - $startTime;

                Log::critical('Fulfil API unreachable', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'duration_seconds' => round($duration, 2),
                    'error' => $e->getMessage(),
                ]);

                throw new FulfilUnavailableException(
                    "Fulfil API is unreachable: {$e->getMessage()}",
                    $endpoint,
                    $method,
                    round($duration, 2),
                    $e
                );
            }

            if ($response->successful()) {
                $duration = microtime(true) - $startTime;
                $result = $response->json();

                // Log slow requests (>15 seconds) for monitoring
                if ($duration > 15) {
                    Log::warning('Fulfil API slow request', [
                        'endpoint' => $endpoint,
                        'method' => $method,
                        'duration_seconds' => round($duration, 2),
                        'record_count' => is_array($result) ? count($result) : 1,
                    ]);
                }

                return $result ?? [];
            }

            // Handle rate limiting (429) with exponential backoff
            // Note: Fulfil sometimes returns 429 for validation errors (not actual rate limits)
            if ($response->status() === 429) {
                $body = $response->json();
                $isValidationError = isset($body['message']) && (
                    str_contains($body['message'], 'cannot exceed') ||
                    str_contains($body['message'], 'invalid') ||
                    str_contains($body['message'], 'Page size')
                );

                // Don't retry validation errors - they'll fail every time
                if (! $isValidationError) {
                    $retryAfter = $response->header('Retry-After');
                    $delaySeconds = $retryAfter
                        ? (int) $retryAfter
                        : $this->calculateBackoff($attempt);

                    Log::warning('Fulfil API rate limited, retrying', [
                        'url' => $url,
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries,
                        'delay_seconds' => $delaySeconds,
                    ]);

                    if ($attempt < $this->maxRetries) {
                        sleep($delaySeconds);

                        continue;
                    }
                }
            }

            $body = $response->json();
            $fulfilMessage = $body['message'] ?? $response->body();
            $lastException = new \RuntimeException("Fulfil API error ({$response->status()}): {$fulfilMessage}");
            Log::error('Fulfil API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'attempt' => $attempt,
            ]);

            // Retry on 500 errors (server errors are often transient)
            if ($response->status() === 500 && $attempt < $this->maxRetries) {
                $delaySeconds = $this->calculateBackoff($attempt);
                Log::warning('Fulfil API server error, retrying', [
                    'url' => $url,
                    'attempt' => $attempt,
                    'delay_seconds' => $delaySeconds,
                ]);
                sleep($delaySeconds);

                continue;
            }

            // For non-retryable errors, throw immediately
            if ($response->status() !== 429 && $response->status() !== 500) {
                throw $lastException;
            }
        }

        throw $lastException ?? new \RuntimeException('Fulfil API error: max retries exceeded');
    }

    /**
     * Calculate backoff delay for a given retry attempt.
     *
     * Uses a similar progression to PIM's queue job backoff [15, 30, 120]
     * but with shorter delays appropriate for synchronous HTTP requests.
     */
    protected function calculateBackoff(int $attempt): int
    {
        // Backoff sequence: 2s, 5s, 10s (scaled down from PIM's 15s, 30s, 120s)
        $backoffSequence = [2, 5, 10];

        return $backoffSequence[min($attempt - 1, count($backoffSequence) - 1)];
    }

    /**
     * Get cached data or fetch from API
     */
    protected function cached(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cacheKey = $this->cachePrefix.$key;
        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear cache for a specific key or all Fulfil cache.
     *
     * When no key is provided, clears all cache entries matching the Fulfil prefix
     * for the current environment, without affecting other application cache.
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($this->cachePrefix.$key);
        } else {
            $this->clearCacheByPrefix($this->cachePrefix);
        }
    }

    /**
     * Clear all cache entries matching a given prefix.
     *
     * Supports database and Redis cache drivers. Falls back to clearing
     * known key patterns for other drivers.
     */
    protected function clearCacheByPrefix(string $prefix): void
    {
        $store = Cache::getStore();
        $laravelPrefix = config('cache.prefix', '');
        $fullPrefix = $laravelPrefix.$prefix;

        if ($store instanceof DatabaseStore) {
            DB::table(config('cache.stores.database.table', 'cache'))
                ->where('key', 'like', $fullPrefix.'%')
                ->delete();

            return;
        }

        if ($store instanceof RedisStore) {
            $redis = $store->connection();
            $cursor = null;
            do {
                $results = $redis->scan($cursor, ['match' => $fullPrefix.'*', 'count' => 100]);
                if (! empty($results)) {
                    $redis->del(...$results);
                }
            } while ($cursor !== 0);

            return;
        }

        // Fallback: clear known Fulfil cache key patterns
        $knownPatterns = [
            'active_customers',
            'products',
            'all_price_lists',
            'all_payment_terms',
            'shipping_terms_categories',
            'metafield_ids',
        ];
        foreach ($knownPatterns as $pattern) {
            Cache::forget($prefix.$pattern);
        }

        Log::debug('Cache driver does not support prefix-based clearing, used known patterns fallback');
    }

    /**
     * Clear all cache entries matching a prefix pattern (e.g., 'invoices_', 'sales_orders_').
     *
     * Supports database and Redis cache drivers.
     */
    public function clearCachePattern(string $pattern): void
    {
        $this->clearCacheByPrefix($this->cachePrefix.$pattern);
    }

    /**
     * Parse Fulfil Decimal objects to float
     */
    protected function parseDecimal(mixed $value): ?float
    {
        if (is_null($value)) {
            return null;
        }
        if (is_array($value) && isset($value['decimal'])) {
            return (float) $value['decimal'];
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Parse Fulfil Date objects to string (Y-m-d format)
     *
     * Fulfil API can return dates in multiple formats:
     * - String: "2024-01-15"
     * - Object: {"__class__": "date", "year": 2024, "month": 1, "day": 15}
     * - Array: {"year": 2024, "month": 1, "day": 15}
     */
    protected function parseDate(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value) && isset($value['year'], $value['month'], $value['day'])) {
            return sprintf('%04d-%02d-%02d', $value['year'], $value['month'], $value['day']);
        }

        return null;
    }

    /**
     * Parse Fulfil DateTime objects to string (Y-m-d H:i:s format)
     *
     * Fulfil API can return datetimes in multiple formats:
     * - String: "2024-01-15T10:30:00"
     * - Object: {"__class__": "datetime", "year": 2024, "month": 1, "day": 15, "hour": 10, ...}
     * - Array: {"year": 2024, "month": 1, "day": 15, "hour": 10, "minute": 30, "second": 0}
     */
    protected function parseDateTime(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        if (is_string($value)) {
            // Handle ISO format strings
            return str_replace('T', ' ', substr($value, 0, 19));
        }
        if (is_array($value) && isset($value['year'], $value['month'], $value['day'])) {
            return sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                $value['year'],
                $value['month'],
                $value['day'],
                $value['hour'] ?? 0,
                $value['minute'] ?? 0,
                $value['second'] ?? 0
            );
        }

        return null;
    }

    // =========================================================================
    // CONTACTS
    // =========================================================================

    /**
     * Get contacts with account manager (wholesale customers)
     */
    public function getActiveCustomers(bool $bustCache = false): array
    {
        $cacheKey = 'active_customers';

        if ($bustCache) {
            $this->clearCache($cacheKey);
        }

        return $this->cached($cacheKey, function () {
            $contacts = $this->fetchContactsWithAccountManager();

            return $this->enrichContacts($contacts);
        });
    }

    /**
     * Get a single customer by party ID.
     *
     * Returns the same structure as getActiveCustomers() but for a single customer.
     */
    public function getCustomer(int $partyId): ?array
    {
        $fields = [
            'id', 'name', 'code', 'active',
            'contact_mechanisms', 'categories',
            'sale_price_list', 'customer_payment_term',
            'account_manager', 'is_customer',
            'receivable', 'receivable_today',
            'create_date',
        ];

        try {
            $contact = $this->request('GET', "model/party.party/{$partyId}", [
                'query' => ['fields' => implode(',', $fields)],
            ]);

            if (empty($contact)) {
                return null;
            }

            // Enrich with related data
            $enriched = $this->enrichContacts([$contact]);

            return $enriched[0] ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch customer from Fulfil', [
                'party_id' => $partyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch contacts that have an account manager assigned
     */
    protected function fetchContactsWithAccountManager(): array
    {
        $fields = [
            'id', 'name', 'code', 'active',
            'contact_mechanisms', 'categories',
            'sale_price_list', 'customer_payment_term',
            'account_manager', 'is_customer',
            'receivable', 'receivable_today',
            'create_date',
        ];

        // Use search_read to filter by account_manager != null
        // Note: Fulfil API max page size is 500
        $response = $this->request('PUT', 'model/party.party/search_read', [
            'json' => [
                'filters' => [['account_manager', '!=', null]],
                'fields' => $fields,
                'limit' => 500,
            ],
        ]);

        return $response;
    }

    /**
     * Enrich contacts with related data
     */
    protected function enrichContacts(array $contacts): array
    {
        if (empty($contacts)) {
            return [];
        }

        // Collect all IDs for batch fetching
        $contactMechanismIds = [];
        $categoryIds = [];
        $priceListIds = [];
        $paymentTermIds = [];

        foreach ($contacts as $contact) {
            $contactMechanismIds = array_merge($contactMechanismIds, $contact['contact_mechanisms'] ?? []);
            $categoryIds = array_merge($categoryIds, $contact['categories'] ?? []);
            if ($contact['sale_price_list']) {
                $priceListIds[] = $contact['sale_price_list'];
            }
            if ($contact['customer_payment_term']) {
                $paymentTermIds[] = $contact['customer_payment_term'];
            }
        }

        // Batch fetch related records
        $contactMechanisms = $this->fetchContactMechanisms(array_unique($contactMechanismIds));
        $categories = $this->fetchCategories(array_unique($categoryIds));
        $priceLists = $this->fetchPriceLists(array_unique($priceListIds));
        $paymentTerms = $this->fetchPaymentTerms(array_unique($paymentTermIds));

        // Map by ID for quick lookup
        $contactMechanismsById = collect($contactMechanisms)->keyBy('id')->toArray();
        $categoriesById = collect($categories)->keyBy('id')->toArray();
        $priceListsById = collect($priceLists)->keyBy('id')->toArray();
        $paymentTermsById = collect($paymentTerms)->keyBy('id')->toArray();

        // Enrich each contact
        return array_map(function ($contact) use ($contactMechanismsById, $categoriesById, $priceListsById, $paymentTermsById) {
            return $this->transformContact($contact, $contactMechanismsById, $categoriesById, $priceListsById, $paymentTermsById);
        }, $contacts);
    }

    /**
     * Transform a single contact with parsed data
     */
    protected function transformContact(array $contact, array $mechanisms, array $categories, array $priceLists, array $paymentTerms): array
    {
        $parsed = [
            'id' => $contact['id'],
            'name' => $contact['name'],
            'code' => $contact['code'] ?? null,
            'create_date' => $contact['create_date'] ?? null,
            'buyers' => [],
            'accounts_payable' => [],
            'other' => [],
            'broker_contacts_from_fulfil' => [],  // Broker contacts parsed from Fulfil
            'broker_company_name_from_fulfil' => null,  // Extracted from broker contact names
            'shelf_life_requirement' => null,
            'vendor_guide' => null,
            'discount_percent' => null,
            'payment_terms' => null,
            'shipping_terms' => null,
            'receivable' => $this->parseDecimal($contact['receivable'] ?? null),
            'receivable_today' => $this->parseDecimal($contact['receivable_today'] ?? null),
        ];

        // Parse contact mechanisms
        foreach ($contact['contact_mechanisms'] ?? [] as $mechanismId) {
            if (! isset($mechanisms[$mechanismId])) {
                continue;
            }
            $mechanism = $mechanisms[$mechanismId];
            $this->parseContactMechanism($mechanism, $parsed);
        }

        // Parse categories for shipping terms
        foreach ($contact['categories'] ?? [] as $categoryId) {
            if (! isset($categories[$categoryId])) {
                continue;
            }
            $category = $categories[$categoryId];
            $this->parseCategoryForShippingTerms($category, $parsed);
        }

        // Parse price list for discount
        if ($contact['sale_price_list'] && isset($priceLists[$contact['sale_price_list']])) {
            $priceList = $priceLists[$contact['sale_price_list']];
            $this->parsePriceListForDiscount($priceList, $parsed);
        }

        // Parse payment term
        if ($contact['customer_payment_term'] && isset($paymentTerms[$contact['customer_payment_term']])) {
            $paymentTerm = $paymentTerms[$contact['customer_payment_term']];
            $parsed['payment_terms'] = $paymentTerm['name'] ?? null;
        }

        return $parsed;
    }

    /**
     * Parse contact mechanism into structured data
     */
    protected function parseContactMechanism(array $mechanism, array &$parsed): void
    {
        $name = $mechanism['name'] ?? '';
        $value = $mechanism['value'] ?? '';

        // Data field: name = "data", value = "shelf_life_req:180"
        if ($name === 'data' && str_contains($value, ':')) {
            [$dataType, $dataValue] = explode(':', $value, 2);
            if ($dataType === 'shelf_life_req') {
                $parsed['shelf_life_requirement'] = $dataValue;
            } elseif ($dataType === 'vendor_guide') {
                $parsed['vendor_guide'] = $dataValue;
            }

            return;
        }

        // Broker contact: name = "Broker (Company Name): Contact Name"
        // Pattern: starts with "Broker" followed by optional "(Company)" then ": Name"
        if (preg_match('/^Broker\s*(?:\(([^)]+)\))?\s*:\s*(.+)$/i', $name, $matches)) {
            $brokerCompany = trim($matches[1] ?? '');
            $contactName = trim($matches[2]);
            $isEmail = str_contains($value, '@') && str_contains($value, '.');

            if ($isEmail) {
                $parsed['broker_contacts_from_fulfil'][] = ['name' => $contactName, 'email' => $value];
                // Extract broker company name from the first broker contact found
                if (! empty($brokerCompany) && empty($parsed['broker_company_name_from_fulfil'])) {
                    $parsed['broker_company_name_from_fulfil'] = $brokerCompany;
                }
            }

            return;
        }

        // Department contact: name = "Accounts Payable: Contact Name"
        if (str_contains($name, ':')) {
            [$department, $contactName] = explode(':', $name, 2);
            $department = trim(strtolower($department));
            $contactName = trim($contactName);

            $isEmail = str_contains($value, '@') && str_contains($value, '.');
            $isUrl = str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
            $isAccountsPayable = str_contains($department, 'accounts payable') || str_contains($department, 'ap');

            // Process emails for all departments, or URLs for Accounts Payable
            if ($isEmail || ($isUrl && $isAccountsPayable)) {
                // Use 'value' key for AP (supports email or URL), 'email' for others
                if ($isAccountsPayable) {
                    $parsed['accounts_payable'][] = ['name' => $contactName, 'value' => $value];
                } elseif ($isEmail && str_contains($department, 'buyer')) {
                    $parsed['buyers'][] = ['name' => $contactName, 'email' => $value];
                } elseif ($isEmail && str_contains($department, 'logistics')) {
                    $parsed['other'][] = ['name' => $contactName, 'email' => $value, 'function' => 'Logistics'];
                } elseif ($isEmail && str_contains($department, 'other')) {
                    // Parse "Other (Function): Name" or "Other: Name"
                    $function = '';
                    if (preg_match('/other\s*\(([^)]+)\)/i', $mechanism['name'], $funcMatch)) {
                        $function = trim($funcMatch[1]);
                    }
                    $parsed['other'][] = ['name' => $contactName, 'email' => $value, 'function' => $function];
                }
            }
        }
    }

    /**
     * Parse category for shipping terms
     */
    protected function parseCategoryForShippingTerms(array $category, array &$parsed): void
    {
        $recName = $category['rec_name'] ?? '';

        if (str_starts_with($recName, 'Shipping Terms / ')) {
            $term = str_replace('Shipping Terms / ', '', $recName);
            $parsed['shipping_terms'] = $term; // "Pickup" or "Delivered"
        }
    }

    /**
     * Parse price list for discount percentage
     */
    protected function parsePriceListForDiscount(array $priceList, array &$parsed): void
    {
        $name = $priceList['name'] ?? '';

        // Format: "Wholesale X% Discount"
        if (preg_match('/(\d+)%\s*Discount/i', $name, $matches)) {
            $parsed['discount_percent'] = (int) $matches[1];
        }
    }

    // =========================================================================
    // SALES ORDERS
    // =========================================================================

    /**
     * Get sales orders for B2B channel
     */
    public function getSalesOrders(array $filters = [], bool $bustCache = false): array
    {
        $cacheKey = 'sales_orders_'.md5(json_encode($filters));

        if ($bustCache) {
            // Clear ALL sales order caches, not just this specific filter combination
            $this->clearCachePattern('sales_orders_');
        }

        return $this->cached($cacheKey, function () use ($filters) {
            $orders = $this->fetchSalesOrders($filters);

            return $this->enrichSalesOrders($orders);
        });
    }

    /**
     * Fetch sales orders from Fulfil with pagination to get all matching records
     */
    protected function fetchSalesOrders(array $filters = []): array
    {
        $channelId = config('fulfil.channels.retail.id');

        $baseFilters = [
            ['channel', '=', $channelId],
            ['state', 'in', ['confirmed', 'processing', 'done']],
        ];

        if (isset($filters['party_id'])) {
            $baseFilters[] = ['party', '=', $filters['party_id']];
        }

        if (isset($filters['state'])) {
            $baseFilters = array_filter($baseFilters, fn ($f) => $f[0] !== 'state');
            $baseFilters[] = ['state', 'in', (array) $filters['state']];
        }

        // Add date filter to limit results
        // Uses sale_date since shipping_end_date may be null for older orders
        if (isset($filters['shipping_date_from'])) {
            $baseFilters[] = ['sale_date', '>=', $filters['shipping_date_from']];
        }

        $fields = [
            'id', 'number', 'reference', 'party',
            'sale_date', 'state', 'shipment_state',
            'total_amount', 'shipping_end_date',
            'lines', 'invoices', 'shipments',
        ];

        // Paginate to get all matching records (Fulfil API max is 500 per request)
        $allResults = [];
        $offset = 0;
        $limit = 500;
        $maxPages = 10; // Safety limit to prevent infinite loops

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->request('PUT', 'model/sale.sale/search_read', [
                'json' => [
                    'filters' => $baseFilters,
                    'fields' => $fields,
                    'limit' => $limit,
                    'offset' => $offset,
                    'order' => [['sale_date', 'DESC']],
                ],
            ]);

            $allResults = array_merge($allResults, $response);

            // If we got fewer results than the limit, we've reached the end
            if (count($response) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return $allResults;
    }

    /**
     * Enrich sales orders with line items and shipment effective dates
     */
    protected function enrichSalesOrders(array $orders): array
    {
        if (empty($orders)) {
            return [];
        }

        // Collect all line IDs and shipment IDs
        $lineIds = [];
        $shipmentIds = [];
        foreach ($orders as $order) {
            $lineIds = array_merge($lineIds, $order['lines'] ?? []);
            $shipmentIds = array_merge($shipmentIds, $order['shipments'] ?? []);
        }

        // Batch fetch lines and shipments
        $lines = $this->fetchSalesOrderLines(array_unique($lineIds));
        $linesById = collect($lines)->keyBy('id')->toArray();

        $shipments = $this->fetchShipments(array_unique($shipmentIds));
        $shipmentsById = collect($shipments)->keyBy('id')->toArray();

        // Transform orders
        return array_map(function ($order) use ($linesById, $shipmentsById) {
            return $this->transformSalesOrder($order, $linesById, $shipmentsById);
        }, $orders);
    }

    /**
     * Transform a sales order
     */
    protected function transformSalesOrder(array $order, array $linesById, array $shipmentsById = []): array
    {
        $lines = [];
        foreach ($order['lines'] ?? [] as $lineId) {
            if (isset($linesById[$lineId])) {
                $line = $linesById[$lineId];
                $lines[] = [
                    'id' => $line['id'],
                    'product_id' => $line['product'] ?? null,
                    'sku' => $this->extractSkuFromRecName($line['rec_name'] ?? ''),
                    'description' => $line['description'] ?? '',
                    'quantity' => $line['quantity'] ?? 0,
                    'unit_price' => $this->parseDecimal($line['unit_price'] ?? null),
                    'amount' => $this->parseDecimal($line['amount'] ?? null),
                ];
            }
        }

        // Get shipment effective_date for done orders
        // Uses the first shipment's effective_date (most orders have one shipment)
        $shipmentEffectiveDate = null;
        foreach ($order['shipments'] ?? [] as $shipmentId) {
            if (isset($shipmentsById[$shipmentId])) {
                $shipment = $shipmentsById[$shipmentId];
                $effectiveDate = $this->parseDate($shipment['effective_date'] ?? null);
                if ($effectiveDate) {
                    $shipmentEffectiveDate = $effectiveDate;
                    break; // Use the first shipment with an effective_date
                }
            }
        }

        return [
            'id' => $order['id'],
            'number' => $order['number'] ?? null,
            'reference' => $order['reference'] ?? null, // Customer PO
            'party_id' => $order['party'] ?? null,
            'sale_date' => $this->parseDate($order['sale_date'] ?? null),
            'state' => $order['state'] ?? null,
            'shipment_state' => $order['shipment_state'] ?? null,
            'total_amount' => $this->parseDecimal($order['total_amount'] ?? null),
            'shipping_end_date' => $this->parseDate($order['shipping_end_date'] ?? null),
            'shipment_effective_date' => $shipmentEffectiveDate,
            'invoice_ids' => $order['invoices'] ?? [],
            'lines' => $lines,
        ];
    }

    /**
     * Extract SKU from rec_name like "[SKU123] Product Name"
     */
    protected function extractSkuFromRecName(string $recName): ?string
    {
        if (preg_match('/^\[([^\]]+)\]/', $recName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // =========================================================================
    // INVOICES
    // =========================================================================

    /**
     * Get invoices for AR B2B
     */
    public function getInvoices(array $filters = [], bool $bustCache = false): array
    {
        $cacheKey = 'invoices_'.md5(json_encode($filters));

        if ($bustCache) {
            // Clear ALL invoice caches, not just this specific filter combination
            $this->clearCachePattern('invoices_');
        }

        return $this->cached($cacheKey, function () use ($filters) {
            return $this->fetchInvoices($filters);
        });
    }

    /**
     * Fetch invoices from Fulfil
     */
    protected function fetchInvoices(array $filters = []): array
    {
        $accountId = config('fulfil.accounts.ar_b2b');

        $baseFilters = [
            ['account', '=', $accountId],
        ];

        if (isset($filters['party_id'])) {
            $baseFilters[] = ['party', '=', $filters['party_id']];
        }

        if (isset($filters['state'])) {
            $baseFilters[] = ['state', 'in', (array) $filters['state']];
        }

        $fields = [
            'id', 'number', 'party', 'state',
            'total_amount', 'balance', 'invoice_date',
            'earliest_due_date', 'payment_term', 'sales',
            'create_date', 'write_date',
        ];

        // Paginate through all invoices (Fulfil API max page size is 500)
        $allInvoices = [];
        $offset = 0;
        $pageSize = 500;

        do {
            $response = $this->request('PUT', 'model/account.invoice/search_read', [
                'json' => [
                    'filters' => $baseFilters,
                    'fields' => $fields,
                    'limit' => $pageSize,
                    'offset' => $offset,
                    'order' => [['invoice_date', 'DESC']],
                ],
            ]);

            $allInvoices = array_merge($allInvoices, $response);
            $offset += $pageSize;
        } while (count($response) === $pageSize);

        // Fetch payment terms
        $paymentTermIds = array_unique(array_filter(array_column($allInvoices, 'payment_term')));
        $paymentTerms = $this->fetchPaymentTerms($paymentTermIds);
        $paymentTermsById = collect($paymentTerms)->keyBy('id')->toArray();

        return array_map(function ($invoice) use ($paymentTermsById) {
            $totalAmount = $this->parseDecimal($invoice['total_amount'] ?? null);
            $balance = $this->parseDecimal($invoice['balance'] ?? null);

            return [
                'id' => $invoice['id'],
                'number' => $invoice['number'] ?? null,
                'party_id' => $invoice['party'] ?? null,
                'state' => $invoice['state'] ?? null,
                'total_amount' => $totalAmount,
                'balance' => $balance,
                'amount_paid' => $totalAmount !== null && $balance !== null ? $totalAmount - $balance : null,
                'invoice_date' => $this->parseDate($invoice['invoice_date'] ?? null),
                'due_date' => $this->parseDate($invoice['earliest_due_date'] ?? null),
                'create_date' => $this->parseDate($invoice['create_date'] ?? null),
                'write_date' => $this->parseDateTime($invoice['write_date'] ?? null),
                'payment_terms' => isset($paymentTermsById[$invoice['payment_term'] ?? null])
                    ? $paymentTermsById[$invoice['payment_term']]['name']
                    : null,
                'sales_order_ids' => $invoice['sales'] ?? [],
            ];
        }, $allInvoices);
    }

    /**
     * Get detailed invoice data for PDF generation.
     *
     * Fetches all data needed to build an InvoicePdfDto:
     * - Invoice core fields (number, date, state, amounts)
     * - Bill-to address from invoice_address
     * - Ship-to address from first customer shipment
     * - Sales person name from employee
     * - Payment term name
     * - Order number from origins or linked sales orders
     * - Line items with account codes for filtering
     *
     * @param  int  $invoiceId  The Fulfil invoice ID
     * @return array Transformed invoice data ready for InvoicePdfDto::fromFulfil()
     *
     * @throws \RuntimeException if invoice not found
     */
    public function getInvoiceForPdf(int $invoiceId): array
    {
        // Fetch the invoice with all needed relations
        $invoice = $this->request('GET', "model/account.invoice/{$invoiceId}", [
            'query' => [
                'fields' => implode(',', [
                    'id', 'number', 'party', 'state', 'reference',
                    'total_amount', 'balance', 'balance_due',
                    'invoice_date', 'earliest_due_date', 'invoice_address',
                    'payment_term', 'employee', 'origins',
                    'lines', 'sales', 'customer_shipments',
                ]),
            ],
        ]);

        if (empty($invoice) || ! isset($invoice['id'])) {
            throw new \RuntimeException("Invoice {$invoiceId} not found");
        }

        // Build the transformed data structure
        $data = [
            'id' => $invoice['id'],
            'number' => $invoice['number'] ?? '',
            'party_id' => $invoice['party'] ?? null,
            'state' => $invoice['state'] ?? '',
            'reference' => $invoice['reference'] ?? null,
            'total_amount' => $this->parseDecimal($invoice['total_amount'] ?? null),
            'balance' => $this->parseDecimal($invoice['balance'] ?? null),
            'balance_due' => $this->parseDecimal($invoice['balance_due'] ?? $invoice['balance'] ?? null),
            'invoice_date' => $this->parseDate($invoice['invoice_date'] ?? null),
            'due_date' => $this->parseDate($invoice['earliest_due_date'] ?? null),
            'origins' => $invoice['origins'] ?? '',
            'invoice_address' => null,
            'customer_shipments' => [],
            'sales_person_name' => '',
            'payment_term_name' => '',
            'order_number' => '',
            'lines' => [],
        ];

        // Fetch bill-to address
        if ($invoice['invoice_address']) {
            $data['invoice_address'] = $this->fetchAddressForPdf($invoice['invoice_address']);
        }

        // Fetch ship-to from first customer shipment
        $shipmentIds = $invoice['customer_shipments'] ?? [];
        if (! empty($shipmentIds)) {
            $shipment = $this->fetchShipmentForPdf($shipmentIds[0]);
            if ($shipment) {
                $data['customer_shipments'][] = $shipment;
            }
        }

        // Fetch employee (sales person) name
        if ($invoice['employee']) {
            $employee = $this->request('GET', "model/company.employee/{$invoice['employee']}", [
                'query' => ['fields' => 'party'],
            ]);
            if ($employee && $employee['party']) {
                $party = $this->request('GET', "model/party.party/{$employee['party']}", [
                    'query' => ['fields' => 'name'],
                ]);
                $data['sales_person_name'] = $party['name'] ?? '';
            }
        }

        // Fetch payment term name
        if ($invoice['payment_term']) {
            $paymentTerm = $this->request('GET', "model/account.invoice.payment_term/{$invoice['payment_term']}", [
                'query' => ['fields' => 'name'],
            ]);
            $data['payment_term_name'] = $paymentTerm['name'] ?? '';
        }

        // Build order number from origins or first sales order
        $data['order_number'] = $invoice['origins'] ?? '';
        if (empty($data['order_number']) && ! empty($invoice['sales'])) {
            $salesOrder = $this->request('GET', "model/sale.sale/{$invoice['sales'][0]}", [
                'query' => ['fields' => 'number'],
            ]);
            $data['order_number'] = $salesOrder['number'] ?? '';
        }

        // Fetch invoice lines with account codes
        $lineIds = $invoice['lines'] ?? [];
        if (! empty($lineIds)) {
            $data['lines'] = $this->fetchInvoiceLinesForPdf($lineIds);
        }

        return $data;
    }

    /**
     * Fetch address details for PDF rendering.
     */
    protected function fetchAddressForPdf(int $addressId): array
    {
        $address = $this->request('GET', "model/party.address/{$addressId}", [
            'query' => [
                'fields' => 'party,street,city,subdivision,zip,country',
            ],
        ]);

        if (empty($address)) {
            return [];
        }

        $result = [
            'street' => $address['street'] ?? '',
            'street2' => null, // Field not available in Fulfil API
            'city' => $address['city'] ?? '',
            'zip' => $address['zip'] ?? '',
            'party_name' => '',
            'subdivision_code' => '',
            'country_name' => '',
        ];

        // Fetch party name
        if ($address['party']) {
            $party = $this->request('GET', "model/party.party/{$address['party']}", [
                'query' => ['fields' => 'name'],
            ]);
            $result['party_name'] = $party['name'] ?? '';
        }

        // Fetch subdivision (state) code
        if ($address['subdivision']) {
            $subdivision = $this->request('GET', "model/country.subdivision/{$address['subdivision']}", [
                'query' => ['fields' => 'code'],
            ]);
            // Code is often like "US-NJ", we want just "NJ"
            $code = $subdivision['code'] ?? '';
            $result['subdivision_code'] = str_contains($code, '-') ? explode('-', $code)[1] : $code;
        }

        // Fetch country name
        if ($address['country']) {
            $country = $this->request('GET', "model/country.country/{$address['country']}", [
                'query' => ['fields' => 'name'],
            ]);
            $result['country_name'] = $country['name'] ?? '';
        }

        return $result;
    }

    /**
     * Fetch customer shipment details for PDF rendering.
     */
    protected function fetchShipmentForPdf(int $shipmentId): ?array
    {
        $shipment = $this->request('GET', "model/stock.shipment.out/{$shipmentId}", [
            'query' => [
                'fields' => 'warehouse,delivery_address',
            ],
        ]);

        if (empty($shipment)) {
            return null;
        }

        $result = [
            'warehouse_name' => '',
            'delivery_party_name' => '',
            'delivery_address' => [],
        ];

        // Fetch warehouse name
        if ($shipment['warehouse']) {
            $warehouse = $this->request('GET', "model/stock.location/{$shipment['warehouse']}", [
                'query' => ['fields' => 'name'],
            ]);
            $result['warehouse_name'] = $warehouse['name'] ?? '';
        }

        // Fetch delivery address
        if ($shipment['delivery_address']) {
            $deliveryAddress = $this->fetchAddressForPdf($shipment['delivery_address']);
            $result['delivery_party_name'] = $deliveryAddress['party_name'] ?? '';
            $result['delivery_address'] = $deliveryAddress;
        }

        return $result;
    }

    /**
     * Fetch invoice lines with product codes and account codes for PDF.
     *
     * @param  array  $lineIds  Invoice line IDs
     * @return array Transformed line data with account_code for filtering
     */
    protected function fetchInvoiceLinesForPdf(array $lineIds): array
    {
        if (empty($lineIds)) {
            return [];
        }

        // Fetch lines in batches
        $lines = $this->batchFetchByIds(
            'model/account.invoice.line',
            $lineIds,
            'id,product,description,quantity,unit_price,amount,account'
        );

        if (empty($lines)) {
            return [];
        }

        // Collect product and account IDs
        $productIds = array_unique(array_filter(array_column($lines, 'product')));
        $accountIds = array_unique(array_filter(array_column($lines, 'account')));

        // Batch fetch products for SKU codes
        $products = [];
        if (! empty($productIds)) {
            $productData = $this->batchFetchByIds('model/product.product', $productIds, 'id,code');
            $products = collect($productData)->keyBy('id')->toArray();
        }

        // Batch fetch accounts for account codes (to filter discount lines)
        $accounts = [];
        if (! empty($accountIds)) {
            $accountData = $this->batchFetchByIds('model/account.account', $accountIds, 'id,code');
            $accounts = collect($accountData)->keyBy('id')->toArray();
        }

        // Transform lines
        return array_map(function ($line) use ($products, $accounts) {
            $productId = $line['product'] ?? null;
            $accountId = $line['account'] ?? null;

            return [
                'product_code' => $productId && isset($products[$productId])
                    ? ($products[$productId]['code'] ?? '')
                    : '',
                'description' => $line['description'] ?? '',
                'quantity' => $this->parseDecimal($line['quantity'] ?? null) ?? 0,
                'unit_price' => $this->parseDecimal($line['unit_price'] ?? null) ?? 0,
                'amount' => $this->parseDecimal($line['amount'] ?? null) ?? 0,
                'account_code' => $accountId && isset($accounts[$accountId])
                    ? ($accounts[$accountId]['code'] ?? '')
                    : '',
            ];
        }, $lines);
    }

    // =========================================================================
    // PRODUCTS
    // =========================================================================

    /**
     * Get products with attributes
     */
    public function getProducts(bool $bustCache = false): array
    {
        $cacheKey = 'products';

        if ($bustCache) {
            $this->clearCache($cacheKey);
        }

        return $this->cached($cacheKey, function () {
            $products = $this->fetchProducts();

            return $this->enrichProducts($products);
        });
    }

    /**
     * Fetch products from Fulfil
     *
     * Filters applied at API level:
     * - active = TRUE
     * - sellable = TRUE
     * - template = ID of "RT Finished Goods" template
     *
     * Filters applied post-fetch in enrichProducts():
     * - Class attribute in ('RT EV', 'RT SE')
     */
    protected function fetchProducts(): array
    {
        // First, get the template ID for "RT Finished Goods"
        $templateId = $this->getTemplateIdByName('RT Finished Goods');
        if (! $templateId) {
            return [];
        }

        $fields = [
            'id', 'code', 'rec_name', 'template',
            'wholesale_list_price', 'active', 'attributes',
        ];

        // Filter by template at API level to get all matching products
        $response = $this->request('PUT', 'model/product.product/search_read', [
            'json' => [
                'filters' => [
                    ['active', '=', true],
                    ['sellable', '=', true],
                    ['template', '=', $templateId],
                ],
                'fields' => $fields,
                'limit' => 500,
            ],
        ]);

        return $response;
    }

    /**
     * Get template ID by name
     */
    protected function getTemplateIdByName(string $name): ?int
    {
        $cacheKey = 'template_id_'.md5($name);

        return $this->cached($cacheKey, function () use ($name) {
            $response = $this->request('PUT', 'model/product.template/search_read', [
                'json' => [
                    'filters' => [['name', '=', $name]],
                    'fields' => ['id'],
                    'limit' => 1,
                ],
            ]);

            return $response[0]['id'] ?? null;
        }, 86400); // Cache for 24 hours
    }

    /**
     * Enrich products with attributes
     *
     * Filters products by:
     * - Class attribute value in ('RT EV', 'RT SE')
     *
     * Note: Template filtering is done at the API level in fetchProducts()
     */
    protected function enrichProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        // Collect all attribute IDs
        $attributeIds = [];
        foreach ($products as $product) {
            $attributeIds = array_merge($attributeIds, $product['attributes'] ?? []);
        }

        // Batch fetch attributes
        $attributes = $this->fetchProductAttributes(array_unique($attributeIds));
        $attributesById = collect($attributes)->keyBy('id')->toArray();

        // Attribute definition IDs we care about
        $classAttrId = config('fulfil.attributes.class');
        $startDateAttrId = config('fulfil.attributes.start_date');
        $endDateAttrId = config('fulfil.attributes.end_date');
        $seasonAttrId = config('fulfil.attributes.season');

        // Allowed Class values for product filtering
        $allowedClassValues = ['RT EV', 'RT SE'];

        $enrichedProducts = array_map(function ($product) use ($attributesById, $classAttrId, $startDateAttrId, $endDateAttrId, $seasonAttrId) {
            // rec_name includes SKU prefix like "[SKU] Product Name" - strip it for clean display
            $name = $product['rec_name'] ?? null;
            if ($name && preg_match('/^\[[^\]]+\]\s*(.+)$/', $name, $matches)) {
                $name = $matches[1];
            }

            $parsed = [
                'id' => $product['id'],
                'sku' => $product['code'] ?? null,
                'name' => $name,
                'template_id' => $product['template'] ?? null,
                'wholesale_list_price' => $this->parseDecimal($product['wholesale_list_price'] ?? null),
                'class' => null,
                'start_date' => null,
                'discontinued_date' => null,
                'season' => null,
            ];

            foreach ($product['attributes'] ?? [] as $attrId) {
                if (! isset($attributesById[$attrId])) {
                    continue;
                }
                $attr = $attributesById[$attrId];
                $attrDefId = $attr['attribute'] ?? null;

                if ($attrDefId === $classAttrId) {
                    $parsed['class'] = $attr['value'] ?? null;
                } elseif ($attrDefId === $startDateAttrId) {
                    $parsed['start_date'] = $attr['value'] ?? null;
                } elseif ($attrDefId === $endDateAttrId) {
                    $parsed['discontinued_date'] = $attr['value'] ?? null;
                } elseif ($attrDefId === $seasonAttrId) {
                    $parsed['season'] = $attr['value'] ?? null;
                }
            }

            return $parsed;
        }, $products);

        // Filter to only include products with Class in allowed values
        return array_values(array_filter($enrichedProducts, function ($product) use ($allowedClassValues) {
            return in_array($product['class'], $allowedClassValues, true);
        }));
    }

    // =========================================================================
    // BATCH FETCH HELPERS
    // =========================================================================

    /**
     * Fetch records by IDs in batches to avoid URI Too Long (414) errors.
     * Splits large ID lists into chunks and merges the results.
     */
    protected function batchFetchByIds(string $endpoint, array $ids, string $fields, int $batchSize = 100): array
    {
        if (empty($ids)) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($ids, $batchSize);

        foreach ($chunks as $chunk) {
            $response = $this->request('GET', $endpoint, [
                'query' => [
                    'ids' => implode(',', $chunk),
                    'fields' => $fields,
                ],
            ]);
            $results = array_merge($results, $response);
        }

        return $results;
    }

    protected function fetchContactMechanisms(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/party.contact_mechanism', $ids, 'id,type,value,name,party,active');
    }

    protected function fetchCategories(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/party.category', $ids, 'id,name,parent,rec_name');
    }

    protected function fetchPriceLists(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/product.price_list', $ids, 'id,name');
    }

    protected function fetchPaymentTerms(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/account.invoice.payment_term', $ids, 'id,name');
    }

    protected function fetchSalesOrderLines(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Batch IDs to avoid URI Too Long (414) errors
        return $this->batchFetchByIds('model/sale.line', $ids, 'id,product,description,quantity,unit_price,amount,rec_name');
    }

    protected function fetchShipments(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Fetch customer shipments with effective_date for done order date calculations
        return $this->batchFetchByIds('model/customer_shipment', $ids, 'id,effective_date,state');
    }

    protected function fetchProductAttributes(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/product.product.attribute', $ids, 'id,attribute,value,value_selection');
    }

    protected function fetchProductTemplates(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->batchFetchByIds('model/product.template', $ids, 'id,name');
    }

    // =========================================================================
    // LOOKUP DATA (for forms)
    // =========================================================================

    /**
     * Get all price lists for dropdown selection.
     * Cached for 24 hours since these rarely change.
     */
    public function getAllPriceLists(bool $bustCache = false): array
    {
        $cacheKey = 'all_price_lists';

        if ($bustCache) {
            $this->clearCache($cacheKey);
        }

        return $this->cached($cacheKey, function () {
            $response = $this->request('PUT', 'model/product.price_list/search_read', [
                'json' => [
                    'filters' => [],
                    'fields' => ['id', 'name'],
                    'limit' => 100,
                ],
            ]);

            // Transform and filter to wholesale price lists
            return collect($response)
                ->filter(fn ($pl) => str_contains(strtolower($pl['name'] ?? ''), 'wholesale'))
                ->map(function ($pl) {
                    // Extract discount percentage from name like "Wholesale 15% Discount"
                    $discount = 0;
                    if (preg_match('/(\d+)%/', $pl['name'], $matches)) {
                        $discount = (int) $matches[1];
                    }

                    return [
                        'id' => $pl['id'],
                        'name' => $pl['name'],
                        'discount_percent' => $discount,
                    ];
                })
                ->sortBy('discount_percent')
                ->values()
                ->toArray();
        }, 86400); // 24 hour cache
    }

    /**
     * Get all payment terms for dropdown selection.
     * Cached for 24 hours since these rarely change.
     */
    public function getAllPaymentTerms(bool $bustCache = false): array
    {
        $cacheKey = 'all_payment_terms';

        if ($bustCache) {
            $this->clearCache($cacheKey);
        }

        return $this->cached($cacheKey, function () {
            $response = $this->request('PUT', 'model/account.invoice.payment_term/search_read', [
                'json' => [
                    'filters' => [],
                    'fields' => ['id', 'name'],
                    'limit' => 100,
                ],
            ]);

            // Sort by extracting days from name (Net 30, Net 60, etc.)
            return collect($response)
                ->map(function ($pt) {
                    $days = 0;
                    if (preg_match('/Net\s*(\d+)/i', $pt['name'], $matches)) {
                        $days = (int) $matches[1];
                    } elseif (strtolower($pt['name']) === 'immediate') {
                        $days = 0;
                    }

                    return [
                        'id' => $pt['id'],
                        'name' => $pt['name'],
                        'days' => $days,
                    ];
                })
                ->sortBy('days')
                ->values()
                ->toArray();
        }, 86400); // 24 hour cache
    }

    /**
     * Get shipping terms categories for dropdown selection.
     * Cached for 24 hours since these rarely change.
     */
    public function getShippingTermsCategories(bool $bustCache = false): array
    {
        $cacheKey = 'shipping_terms_categories';

        if ($bustCache) {
            $this->clearCache($cacheKey);
        }

        return $this->cached($cacheKey, function () {
            $response = $this->request('PUT', 'model/party.category/search_read', [
                'json' => [
                    'filters' => [['rec_name', 'ilike', 'Shipping Terms / %']],
                    'fields' => ['id', 'name', 'rec_name'],
                    'limit' => 50,
                ],
            ]);

            return collect($response)
                ->map(fn ($cat) => [
                    'id' => $cat['id'],
                    'name' => $cat['name'], // "Pickup" or "Delivered"
                ])
                ->sortBy('name')
                ->values()
                ->toArray();
        }, 86400); // 24 hour cache
    }

    // =========================================================================
    // CUSTOMER MANAGEMENT (create/update)
    // =========================================================================

    /**
     * Default account manager ID (Matt Cameron)
     */
    protected const DEFAULT_ACCOUNT_MANAGER_ID = 58;

    /**
     * Create a new customer in Fulfil.
     *
     * @param  array  $data  Customer data with keys:
     *                       - name: string (required)
     *                       - sale_price_list: int (required) - price list ID
     *                       - customer_payment_term: int (required) - payment term ID
     *                       - shipping_terms_category_id: int (required) - category ID for Pickup/Delivered
     *                       - shelf_life_requirement: int (required) - days
     *                       - vendor_guide: string|null (optional) - URL
     *                       - buyers: array (required, min 1) - [{name, email}, ...]
     *                       - accounts_payable: array (optional) - [{name, value}, ...] where value is email or URL
     *                       - other: array (optional) - [{name, email, function}, ...]
     * @return array The created customer with ID
     *
     * @throws \RuntimeException on API errors
     */
    public function createCustomer(array $data): array
    {
        // 1. Create the party (customer) record
        $partyData = [
            'name' => $data['name'],
            'is_customer' => true,
            'account_manager' => self::DEFAULT_ACCOUNT_MANAGER_ID,
            'sale_price_list' => $data['sale_price_list'],
            'customer_payment_term' => $data['customer_payment_term'],
        ];

        // Fulfil auto-generates a sequential `code` for each new party.
        // Occasionally the sequence collides with an existing code, so we
        // retry a few times to let the sequence advance past the collision.
        $partyId = null;
        $maxCodeRetries = 3;

        for ($codeAttempt = 1; $codeAttempt <= $maxCodeRetries; $codeAttempt++) {
            try {
                $partyResponse = $this->request('POST', 'model/party.party', [
                    'json' => [$partyData],
                ]);

                // API returns array of created records: [['id' => 123, 'rec_name' => 'Name'], ...]
                $partyId = $partyResponse[0]['id'] ?? null;
                if (! $partyId) {
                    throw new \RuntimeException('Failed to create customer: no ID returned');
                }

                break; // Success — exit retry loop
            } catch (\RuntimeException $e) {
                $isCodeConflict = str_contains($e->getMessage(), 'code')
                    && str_contains($e->getMessage(), 'unique');

                if ($isCodeConflict && $codeAttempt < $maxCodeRetries) {
                    Log::warning('Fulfil party code collision, retrying', [
                        'attempt' => $codeAttempt,
                        'error' => $e->getMessage(),
                    ]);
                    sleep(1); // Brief pause before retry

                    continue;
                }

                throw $e; // Not a code collision, or retries exhausted
            }
        }

        // 2. Add shipping terms category
        $this->addCategoryToParty($partyId, $data['shipping_terms_category_id']);

        // 3. Create contact mechanisms
        $contactMechanisms = [];

        // Buyers (required)
        foreach ($data['buyers'] ?? [] as $buyer) {
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => 'Buyer: '.$buyer['name'],
                'value' => $buyer['email'],
            ];
        }

        // Accounts Payable (optional, can be email or URL)
        foreach ($data['accounts_payable'] ?? [] as $ap) {
            $isUrl = str_starts_with($ap['value'], 'http://') || str_starts_with($ap['value'], 'https://');
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => $isUrl ? 'website' : 'email',
                'name' => 'Accounts Payable: '.$ap['name'],
                'value' => $ap['value'],
            ];
        }

        // Other contacts (optional)
        foreach ($data['other'] ?? [] as $other) {
            $function = trim($other['function'] ?? '');
            $mechName = $function
                ? "Other ({$function}): {$other['name']}"
                : "Other: {$other['name']}";
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => $mechName,
                'value' => $other['email'],
            ];
        }

        // Broker contacts (optional)
        // Format: "Broker (Company Name): Contact Name"
        $brokerCompanyName = $data['broker_company_name'] ?? '';
        foreach ($data['broker_contacts'] ?? [] as $broker) {
            $mechName = $brokerCompanyName
                ? "Broker ({$brokerCompanyName}): {$broker['name']}"
                : "Broker: {$broker['name']}";
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => $mechName,
                'value' => $broker['email'],
            ];
        }

        // Shelf life requirement (required)
        // Using type 'email' so it's exposed to data warehouse
        $contactMechanisms[] = [
            'party' => $partyId,
            'type' => 'email',
            'name' => 'data',
            'value' => 'shelf_life_req:'.$data['shelf_life_requirement'],
        ];

        // Vendor guide (optional)
        // Using type 'email' so it's exposed to data warehouse
        if (! empty($data['vendor_guide'])) {
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => 'data',
                'value' => 'vendor_guide:'.$data['vendor_guide'],
            ];
        }

        // Batch create all contact mechanisms
        if (! empty($contactMechanisms)) {
            $this->request('POST', 'model/party.contact_mechanism', [
                'json' => $contactMechanisms,
            ]);
        }

        // 4. Sync metafields (shelf_life, broker, broker_commission)
        // AR metafields are handled separately by the controller after creation.
        // Shelf life is dual-written: contact mechanism (above) + metafield (here).
        $metafields = $this->buildMetafieldPayload($data);
        if (! empty($metafields)) {
            try {
                $this->request('PUT', "model/party.party/{$partyId}", [
                    'json' => [
                        'metafields' => ['set' => $metafields],
                    ],
                ]);
            } catch (\Exception $e) {
                // Log but don't fail customer creation for metafield sync
                Log::warning('Failed to sync metafields for new customer', [
                    'party_id' => $partyId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clear the active customers cache since we added a new one
        $this->clearCache('active_customers');

        return [
            'id' => $partyId,
            'name' => $data['name'],
        ];
    }

    /**
     * Update an existing customer in Fulfil.
     *
     * @param  int  $partyId  The customer's party ID
     * @param  array  $data  Customer data (same structure as createCustomer)
     * @return array The updated customer
     */
    public function updateCustomer(int $partyId, array $data): array
    {
        // 1. Update the party record
        // Fulfil's API cannot combine party fields and metafield "set"
        // operations in a single PUT (returns 500), so we split them into
        // two sequential PUTs within this single backend call.
        $partyData = [];

        if (isset($data['name'])) {
            $partyData['name'] = $data['name'];
        }
        if (isset($data['sale_price_list'])) {
            $partyData['sale_price_list'] = $data['sale_price_list'];
        }
        if (isset($data['customer_payment_term'])) {
            $partyData['customer_payment_term'] = $data['customer_payment_term'];
        }

        if (! empty($partyData)) {
            $this->request('PUT', "model/party.party/{$partyId}", [
                'json' => $partyData,
            ]);
        }

        // 2. Update metafields if provided (separate PUT — Fulfil cannot
        //    combine party fields and metafield "set" in a single PUT)
        $metafields = $this->buildMetafieldPayload($data);
        if (! empty($metafields)) {
            $this->request('PUT', "model/party.party/{$partyId}", [
                'json' => [
                    'metafields' => ['set' => $metafields],
                ],
            ]);

            Log::info('Updated customer metafields', [
                'party_id' => $partyId,
            ]);
        }

        // 3. Update shipping terms category if provided
        if (isset($data['shipping_terms_category_id'])) {
            $this->updateShippingTermsCategory($partyId, $data['shipping_terms_category_id']);
        }

        // 4. Sync contacts and/or data fields stored as contact mechanisms
        if ($this->hasContactUpdates($data)) {
            // Full sync: contacts + data fields (buyers, AP, other, broker, shelf_life, vendor_guide)
            $this->syncContactMechanisms($partyId, $data);
        } elseif ($this->hasDataFieldUpdates($data)) {
            // Lightweight sync: only data fields (shelf_life, vendor_guide)
            $this->syncDataFields($partyId, $data);
        }

        // Clear the active customers cache
        $this->clearCache('active_customers');

        return [
            'id' => $partyId,
            'name' => $data['name'] ?? null,
        ];
    }

    /**
     * Build metafield set payload from customer data array.
     *
     * Handles both AR settings (ar_* prefixed keys) and customer detail
     * metafields (shelf_life_requirement, broker, broker_commission).
     * Returns an empty array if no metafield-backed values are present.
     *
     * @return array<int, array{field: int, value: string|null}>
     */
    protected function buildMetafieldPayload(array $data): array
    {
        $metafieldValues = [];

        // AR settings (ar_* prefixed keys)
        if (array_key_exists('ar_edi', $data)) {
            $metafieldValues['edi'] = $data['ar_edi'] ? 'true' : 'false';
        }
        if (array_key_exists('ar_consolidated_invoicing', $data)) {
            $metafieldValues['consolidated_invoicing'] = $data['ar_consolidated_invoicing'] ? 'true' : 'false';
        }
        if (array_key_exists('ar_requires_customer_skus', $data)) {
            $metafieldValues['requires_customer_skus'] = $data['ar_requires_customer_skus'] ? 'true' : 'false';
        }
        if (array_key_exists('ar_invoice_discount', $data) && $data['ar_invoice_discount'] !== null && $data['ar_invoice_discount'] !== '') {
            $metafieldValues['invoice_discount'] = (string) $data['ar_invoice_discount'];
        }

        // Customer detail metafields
        if (isset($data['shelf_life_requirement'])) {
            $metafieldValues['shelf_life'] = (string) $data['shelf_life_requirement'];
        }
        if (array_key_exists('broker', $data)) {
            $metafieldValues['broker'] = $data['broker'] ? 'true' : 'false';
        }
        if (array_key_exists('broker_commission', $data) && $data['broker_commission'] !== '' && $data['broker_commission'] !== null) {
            $metafieldValues['broker_commission'] = (string) $data['broker_commission'];
        }

        if (empty($metafieldValues)) {
            return [];
        }

        $metafieldIds = $this->getMetafieldIds();
        if (empty($metafieldIds)) {
            throw new \RuntimeException(
                'Metafields could not be saved: metafield IDs are not configured for the '
                .$this->getEnvironment().' environment. Run `php artisan fulfil:discover-metafields --fulfil-env='
                .$this->getEnvironment().'` to find them.'
            );
        }

        $setMetafields = [];
        foreach ($metafieldValues as $code => $value) {
            $fieldId = $metafieldIds[$code] ?? null;
            if (! $fieldId) {
                Log::warning('Metafield ID not configured', [
                    'code' => $code,
                    'environment' => $this->getEnvironment(),
                ]);

                continue;
            }
            $setMetafields[] = [
                'field' => (int) $fieldId,
                'value' => $value,
            ];
        }

        return $setMetafields;
    }

    /**
     * Add a category to a party.
     */
    protected function addCategoryToParty(int $partyId, int $categoryId): void
    {
        $this->request('PUT', "model/party.party/{$partyId}", [
            'json' => [
                'categories' => [['add', [$categoryId]]],
            ],
        ]);
    }

    /**
     * Update shipping terms category for a party.
     * Removes old shipping terms category and adds new one.
     */
    protected function updateShippingTermsCategory(int $partyId, int $newCategoryId): void
    {
        // Get current categories
        $party = $this->request('GET', "model/party.party/{$partyId}", [
            'query' => ['fields' => 'categories'],
        ]);

        $currentCategories = $party['categories'] ?? [];

        // Get all shipping terms category IDs
        $shippingCategories = $this->getShippingTermsCategories();
        $shippingCategoryIds = array_column($shippingCategories, 'id');

        // Build category operations: remove old shipping terms, add new one
        $operations = [];

        // Remove existing shipping terms categories
        foreach ($currentCategories as $catId) {
            if (in_array($catId, $shippingCategoryIds) && $catId !== $newCategoryId) {
                $operations[] = ['remove', [$catId]];
            }
        }

        // Add new category if not already present
        if (! in_array($newCategoryId, $currentCategories)) {
            $operations[] = ['add', [$newCategoryId]];
        }

        if (! empty($operations)) {
            $this->request('PUT', "model/party.party/{$partyId}", [
                'json' => [
                    'categories' => $operations,
                ],
            ]);
        }
    }

    /**
     * Check if data contains actual contact updates (buyers, AP, other, broker).
     */
    protected function hasContactUpdates(array $data): bool
    {
        return isset($data['buyers'])
            || isset($data['accounts_payable'])
            || isset($data['other'])
            || isset($data['broker_contacts']);
    }

    /**
     * Check if data contains data field updates stored as contact mechanisms.
     */
    protected function hasDataFieldUpdates(array $data): bool
    {
        return isset($data['shelf_life_requirement'])
            || array_key_exists('vendor_guide', $data);
    }

    /**
     * Sync only data fields (shelf_life, vendor_guide) stored as contact mechanisms.
     *
     * This is a lightweight alternative to syncContactMechanisms for when only
     * data fields are being updated (e.g. details-only saves). It fetches only
     * the "data" mechanisms instead of all contacts for the party.
     */
    protected function syncDataFields(int $partyId, array $data): void
    {
        $existing = $this->request('PUT', 'model/party.contact_mechanism/search_read', [
            'json' => [
                'filters' => [['party', '=', $partyId], ['name', '=', 'data']],
                'fields' => ['id', 'type', 'name', 'value'],
                'limit' => 100,
            ],
        ]);

        $toCreate = [];
        $toUpdate = [];
        $toDelete = [];

        if (isset($data['shelf_life_requirement'])) {
            $existingShelfLife = collect($existing)->first(fn ($m) => str_starts_with($m['value'] ?? '', 'shelf_life_req:'));

            if ($existingShelfLife) {
                $newValue = 'shelf_life_req:'.$data['shelf_life_requirement'];
                if ($existingShelfLife['value'] !== $newValue) {
                    $toUpdate[] = ['id' => $existingShelfLife['id'], 'value' => $newValue];
                }
            } else {
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'shelf_life_req:'.$data['shelf_life_requirement'],
                ];
            }
        }

        if (array_key_exists('vendor_guide', $data)) {
            $existingVendorGuide = collect($existing)->first(fn ($m) => str_starts_with($m['value'] ?? '', 'vendor_guide:'));

            if ($existingVendorGuide) {
                if (empty($data['vendor_guide'])) {
                    $toDelete[] = $existingVendorGuide['id'];
                } else {
                    $newValue = 'vendor_guide:'.$data['vendor_guide'];
                    if ($existingVendorGuide['value'] !== $newValue) {
                        $toUpdate[] = ['id' => $existingVendorGuide['id'], 'value' => $newValue];
                    }
                }
            } elseif (! empty($data['vendor_guide'])) {
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'vendor_guide:'.$data['vendor_guide'],
                ];
            }
        }

        if (! empty($toCreate)) {
            $this->request('POST', 'model/party.contact_mechanism', [
                'json' => $toCreate,
            ]);
        }

        foreach ($toUpdate as $update) {
            $id = $update['id'];
            unset($update['id']);
            $this->request('PUT', "model/party.contact_mechanism/{$id}", [
                'json' => $update,
            ]);
        }

        if (! empty($toDelete)) {
            $this->request('PUT', 'model/party.contact_mechanism/delete', [
                'json' => [$toDelete],
            ]);
        }
    }

    /**
     * Sync contact mechanisms for a party.
     * Handles create, update, and delete of contact mechanisms.
     */
    protected function syncContactMechanisms(int $partyId, array $data): void
    {
        // Fetch existing contact mechanisms for this party
        $existing = $this->request('PUT', 'model/party.contact_mechanism/search_read', [
            'json' => [
                'filters' => [['party', '=', $partyId]],
                'fields' => ['id', 'type', 'name', 'value'],
                'limit' => 100,
            ],
        ]);

        $toCreate = [];
        $toUpdate = [];
        $toDelete = [];

        // Track which existing mechanisms we've matched
        $matchedIds = [];

        // Helper to find existing mechanism by name pattern
        $findExisting = function (string $namePrefix) use ($existing, &$matchedIds) {
            foreach ($existing as $mech) {
                if (str_starts_with($mech['name'] ?? '', $namePrefix) && ! in_array($mech['id'], $matchedIds)) {
                    return $mech;
                }
            }

            return null;
        };

        // Process buyers
        if (isset($data['buyers'])) {
            // Mark all existing buyer mechanisms for potential deletion
            $existingBuyers = array_filter($existing, fn ($m) => str_starts_with($m['name'] ?? '', 'Buyer:'));

            foreach ($data['buyers'] as $buyer) {
                $mechName = 'Buyer: '.$buyer['name'];
                $found = collect($existingBuyers)->firstWhere('name', $mechName);

                if ($found) {
                    $matchedIds[] = $found['id'];
                    if ($found['value'] !== $buyer['email']) {
                        $toUpdate[] = ['id' => $found['id'], 'value' => $buyer['email']];
                    }
                } else {
                    $toCreate[] = [
                        'party' => $partyId,
                        'type' => 'email',
                        'name' => $mechName,
                        'value' => $buyer['email'],
                    ];
                }
            }

            // Delete unmatched existing buyers
            foreach ($existingBuyers as $eb) {
                if (! in_array($eb['id'], $matchedIds)) {
                    $toDelete[] = $eb['id'];
                }
            }
        }

        // Process accounts payable
        if (isset($data['accounts_payable'])) {
            $existingAP = array_filter($existing, fn ($m) => str_starts_with($m['name'] ?? '', 'Accounts Payable:'));

            foreach ($data['accounts_payable'] as $ap) {
                $mechName = 'Accounts Payable: '.$ap['name'];
                $isUrl = str_starts_with($ap['value'], 'http://') || str_starts_with($ap['value'], 'https://');
                $found = collect($existingAP)->firstWhere('name', $mechName);

                if ($found) {
                    $matchedIds[] = $found['id'];
                    if ($found['value'] !== $ap['value']) {
                        $toUpdate[] = [
                            'id' => $found['id'],
                            'value' => $ap['value'],
                            'type' => $isUrl ? 'website' : 'email',
                        ];
                    }
                } else {
                    $toCreate[] = [
                        'party' => $partyId,
                        'type' => $isUrl ? 'website' : 'email',
                        'name' => $mechName,
                        'value' => $ap['value'],
                    ];
                }
            }

            foreach ($existingAP as $eap) {
                if (! in_array($eap['id'], $matchedIds)) {
                    $toDelete[] = $eap['id'];
                }
            }
        }

        // Process other contacts (also match legacy "Logistics:" entries)
        if (isset($data['other'])) {
            $existingOther = array_filter($existing, fn ($m) => str_starts_with($m['name'] ?? '', 'Other:') ||
                str_starts_with($m['name'] ?? '', 'Other (') ||
                str_starts_with($m['name'] ?? '', 'Logistics:')
            );

            foreach ($data['other'] as $other) {
                $function = trim($other['function'] ?? '');
                $mechName = $function
                    ? "Other ({$function}): {$other['name']}"
                    : "Other: {$other['name']}";

                // Try to find by exact name first
                $found = collect($existingOther)->firstWhere('name', $mechName);

                // If not found and function is 'Logistics', try legacy "Logistics:" format
                if (! $found && strtolower($function) === 'logistics') {
                    $legacyName = 'Logistics: '.$other['name'];
                    $found = collect($existingOther)->firstWhere('name', $legacyName);
                }

                if ($found) {
                    $matchedIds[] = $found['id'];
                    // Update if email or name format changed
                    if ($found['value'] !== $other['email'] || $found['name'] !== $mechName) {
                        $toUpdate[] = [
                            'id' => $found['id'],
                            'name' => $mechName,
                            'value' => $other['email'],
                        ];
                    }
                } else {
                    $toCreate[] = [
                        'party' => $partyId,
                        'type' => 'email',
                        'name' => $mechName,
                        'value' => $other['email'],
                    ];
                }
            }

            foreach ($existingOther as $eo) {
                if (! in_array($eo['id'], $matchedIds)) {
                    $toDelete[] = $eo['id'];
                }
            }
        }

        // Process broker contacts
        // Format: "Broker (Company Name): Contact Name"
        if (isset($data['broker_contacts'])) {
            $brokerCompanyName = $data['broker_company_name'] ?? '';
            $existingBrokers = array_filter($existing, fn ($m) => preg_match('/^Broker\s*(?:\([^)]*\))?\s*:/i', $m['name'] ?? ''));

            foreach ($data['broker_contacts'] as $broker) {
                // Build mechanism name with company name if provided
                $mechName = $brokerCompanyName
                    ? "Broker ({$brokerCompanyName}): {$broker['name']}"
                    : "Broker: {$broker['name']}";

                // Try to find existing mechanism by contact name (ignore company name changes)
                $found = null;
                foreach ($existingBrokers as $eb) {
                    // Extract contact name from existing mechanism
                    if (preg_match('/^Broker\s*(?:\([^)]*\))?\s*:\s*(.+)$/i', $eb['name'], $matches)) {
                        if (trim($matches[1]) === $broker['name']) {
                            $found = $eb;
                            break;
                        }
                    }
                }

                if ($found) {
                    $matchedIds[] = $found['id'];
                    // Update if email or company name changed
                    if ($found['value'] !== $broker['email'] || $found['name'] !== $mechName) {
                        $toUpdate[] = [
                            'id' => $found['id'],
                            'name' => $mechName,
                            'value' => $broker['email'],
                        ];
                    }
                } else {
                    $toCreate[] = [
                        'party' => $partyId,
                        'type' => 'email',
                        'name' => $mechName,
                        'value' => $broker['email'],
                    ];
                }
            }

            foreach ($existingBrokers as $eb) {
                if (! in_array($eb['id'], $matchedIds)) {
                    $toDelete[] = $eb['id'];
                }
            }
        }

        // Process shelf life requirement
        if (isset($data['shelf_life_requirement'])) {
            $existingShelfLife = collect($existing)->first(fn ($m) => $m['name'] === 'data' && str_starts_with($m['value'] ?? '', 'shelf_life_req:'));

            if ($existingShelfLife) {
                $matchedIds[] = $existingShelfLife['id'];
                $newValue = 'shelf_life_req:'.$data['shelf_life_requirement'];
                if ($existingShelfLife['value'] !== $newValue) {
                    $toUpdate[] = ['id' => $existingShelfLife['id'], 'value' => $newValue];
                }
            } else {
                // Using type 'email' so it's exposed to data warehouse
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'shelf_life_req:'.$data['shelf_life_requirement'],
                ];
            }
        }

        // Process vendor guide
        if (array_key_exists('vendor_guide', $data)) {
            $existingVendorGuide = collect($existing)->first(fn ($m) => $m['name'] === 'data' && str_starts_with($m['value'] ?? '', 'vendor_guide:'));

            if ($existingVendorGuide) {
                $matchedIds[] = $existingVendorGuide['id'];
                if (empty($data['vendor_guide'])) {
                    // Delete if cleared
                    $toDelete[] = $existingVendorGuide['id'];
                } else {
                    $newValue = 'vendor_guide:'.$data['vendor_guide'];
                    if ($existingVendorGuide['value'] !== $newValue) {
                        $toUpdate[] = ['id' => $existingVendorGuide['id'], 'value' => $newValue];
                    }
                }
            } elseif (! empty($data['vendor_guide'])) {
                // Using type 'email' so it's exposed to data warehouse
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'vendor_guide:'.$data['vendor_guide'],
                ];
            }
        }

        // Execute operations
        if (! empty($toCreate)) {
            $this->request('POST', 'model/party.contact_mechanism', [
                'json' => $toCreate,
            ]);
        }

        foreach ($toUpdate as $update) {
            $id = $update['id'];
            unset($update['id']);
            $this->request('PUT', "model/party.contact_mechanism/{$id}", [
                'json' => $update,
            ]);
        }

        if (! empty($toDelete)) {
            $this->request('PUT', 'model/party.contact_mechanism/delete', [
                'json' => [$toDelete],
            ]);
        }
    }

    // =========================================================================
    // METAFIELDS (AR Automation)
    // =========================================================================

    /**
     * Debug method to dump raw metafield data from contacts.
     */
    /**
     * Get all metafield definitions from the metafield.field model.
     *
     * Returns an array of metafield definitions with id and rec_name.
     */
    public function getMetafieldDefinitions(): array
    {
        return $this->request('PUT', 'model/metafield.field/search_read', [
            'json' => [[], null, 200, null, null],
        ]);
    }

    public function debugContactMetafields(int $limit = 20): array
    {
        $contacts = $this->request('PUT', 'model/party.party/search_read', [
            'json' => [
                [['is_customer', '=', true]],
                0,
                $limit,
                null,
                ['id', 'name', 'metafields'],
            ],
        ]);

        $result = [];
        foreach ($contacts as $contact) {
            if (! empty($contact['metafields'])) {
                $result[] = [
                    'id' => $contact['id'],
                    'name' => $contact['name'],
                    'metafields' => $contact['metafields'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get metafield IDs for the current environment.
     * Falls back to discovering IDs by code if not configured.
     */
    protected function getMetafieldIds(): array
    {
        $env = $this->getEnvironment();
        $ids = config("fulfil.metafields.{$env}", []);

        // If IDs are configured, use them
        if (! empty(array_filter($ids))) {
            return $ids;
        }

        // Otherwise, try to discover them (useful when sandbox refreshes)
        Log::info('Metafield IDs not configured, attempting discovery', ['environment' => $env]);

        return $this->discoverMetafieldIdsByCode();
    }

    /**
     * Discover metafield IDs by querying contacts that have values.
     * Returns array of code => field_id mappings.
     */
    protected function discoverMetafieldIdsByCode(): array
    {
        return $this->cached('metafield_ids', function () {
            // First, try scanning contacts that have metafield values
            $contactsWithMetafields = $this->debugContactMetafields(50);

            $ids = [];
            foreach ($contactsWithMetafields as $contact) {
                foreach ($contact['metafields'] as $mf) {
                    $code = $mf['code'] ?? null;
                    $fieldId = $mf['field'] ?? null;
                    if ($code && $fieldId && ! isset($ids[$code])) {
                        $ids[$code] = $fieldId;
                    }
                }
            }

            if (! empty($ids)) {
                Log::info('Discovered metafield IDs by code from contacts', ['ids' => $ids]);

                return $ids;
            }

            // Fallback: query the metafield.field model for definitions
            // and match by name to known AR metafield names
            try {
                $nameToCode = [
                    'EDI' => 'edi',
                    'Consolidated Invoicing' => 'consolidated_invoicing',
                    'Invoice Requires Customer SKUs' => 'requires_customer_skus',
                    'Invoice Discount' => 'invoice_discount',
                    'Shelf Life Required on Arrival (Days)' => 'shelf_life',
                    'Broker' => 'broker',
                    'Broker Commission' => 'broker_commission',
                ];

                $definitions = $this->getMetafieldDefinitions();
                foreach ($definitions as $def) {
                    $name = $def['rec_name'] ?? '';
                    if (isset($nameToCode[$name])) {
                        $ids[$nameToCode[$name]] = $def['id'];
                    }
                }

                if (! empty($ids)) {
                    Log::info('Discovered metafield IDs from metafield.field model', ['ids' => $ids]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to discover metafield IDs from metafield.field model', [
                    'error' => $e->getMessage(),
                ]);
            }

            return $ids;
        });
    }

    /**
     * Discover all Contact metafield definitions from Fulfil.
     *
     * Used to find the metafield IDs for AR automation fields.
     */
    public function discoverContactMetafields(): array
    {
        // Try ir.model.field with metafield-related filters
        $modelFieldEndpoints = [
            ['model/ir.model.field/search_read', [['model.model', '=', 'party.party'], ['name', 'ilike', '%metafield%']]],
            ['model/ir.model.field/search_read', [['model.model', '=', 'party.party']]],
        ];

        foreach ($modelFieldEndpoints as [$endpoint, $filters]) {
            try {
                $response = $this->request('PUT', $endpoint, [
                    'json' => [
                        $filters,
                        null, // offset
                        100,  // limit
                        null, // order
                        ['id', 'name', 'field_description', 'ttype', 'model'],
                    ],
                ]);

                // Filter for metafield-like entries
                $metafields = array_filter($response, function ($field) {
                    $name = strtolower($field['name'] ?? '');
                    $desc = strtolower($field['field_description'] ?? '');

                    return str_contains($name, 'metafield') ||
                           str_contains($name, 'edi') ||
                           str_contains($name, 'consolidated') ||
                           str_contains($name, 'invoice_discount') ||
                           str_contains($name, 'customer_sku') ||
                           str_contains($desc, 'metafield');
                });

                if (! empty($metafields)) {
                    return array_values(array_map(function ($f) {
                        return [
                            'id' => $f['id'],
                            'code' => $f['name'] ?? 'unknown',
                            'name' => $f['field_description'] ?? $f['name'] ?? 'Unknown',
                            'type' => $f['ttype'] ?? 'unknown',
                        ];
                    }, $metafields));
                }
            } catch (\Exception $e) {
                Log::debug('Model field query failed', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Try getting metafields directly from contacts endpoint
        // This queries for metafield definitions, not values
        try {
            $response = $this->request('GET', 'model/party.party/fields', []);
            if (isset($response['metafields'])) {
                Log::info('Found metafields field definition', ['def' => $response['metafields']]);
            }
        } catch (\Exception $e) {
            Log::debug('Fields endpoint failed', ['error' => $e->getMessage()]);
        }

        // Try to read metafields from multiple contacts to find any that have values
        try {
            $contacts = $this->request('PUT', 'model/party.party/search_read', [
                'json' => [
                    [['is_customer', '=', true]],
                    0,    // offset
                    10,   // limit - check a few contacts
                    null, // order
                    ['id', 'name', 'metafields'],
                ],
            ]);

            $allMetafields = [];
            foreach ($contacts as $contact) {
                if (! empty($contact['metafields'])) {
                    foreach ($contact['metafields'] as $mf) {
                        $fieldId = $mf['field'] ?? null;
                        if ($fieldId && ! isset($allMetafields[$fieldId])) {
                            $allMetafields[$fieldId] = [
                                'id' => $fieldId,
                                'code' => $mf['code'] ?? 'field_'.$fieldId,
                                'name' => $mf['name'] ?? 'Metafield '.$fieldId,
                                'type' => $mf['type'] ?? 'unknown',
                                'sample_value' => $mf['value'] ?? null,
                            ];
                        }
                    }
                }
            }

            if (! empty($allMetafields)) {
                return array_values($allMetafields);
            }
        } catch (\Exception $e) {
            Log::debug('Sample contacts metafield query failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get metafield values for a contact.
     *
     * @param  int  $partyId  The Fulfil party ID
     * @return array Associative array of metafield code => value
     */
    public function getContactMetafields(int $partyId): array
    {
        $metafieldIds = $this->getMetafieldIds();

        if (empty($metafieldIds)) {
            return [];
        }

        // Fetch the contact with metafields
        $response = $this->request('PUT', 'model/party.party/search_read', [
            'json' => [
                [['id', '=', $partyId]],
                null, // offset
                1,    // limit
                null, // order
                ['id', 'metafields'],
            ],
        ]);

        if (empty($response)) {
            return [];
        }

        $contact = $response[0];
        $metafields = $contact['metafields'] ?? [];

        // Map metafield IDs to codes
        $idToCode = array_flip(array_filter($metafieldIds));
        $result = [];

        foreach ($metafields as $mf) {
            $fieldId = $mf['field'] ?? null;
            // Handle field returned as object {"id": 120, "name": "...", "code": "..."}
            // or as array [id, name], or as plain integer
            if (is_array($fieldId)) {
                $fieldId = $fieldId['id'] ?? $fieldId[0] ?? null;
            }
            if ($fieldId && isset($idToCode[$fieldId])) {
                $code = $idToCode[$fieldId];
                $result[$code] = $mf['value'] ?? null;
            }
        }

        return $result;
    }

    /**
     * Update metafield values for a contact.
     *
     * @param  int  $partyId  The Fulfil party ID
     * @param  array  $values  Associative array of metafield code => value
     */
    public function updateContactMetafields(int $partyId, array $values): void
    {
        $metafieldIds = $this->getMetafieldIds();

        if (empty($metafieldIds)) {
            Log::warning('No metafield IDs configured for Fulfil environment', [
                'environment' => $this->getEnvironment(),
            ]);

            return;
        }

        $setMetafields = [];

        foreach ($values as $code => $value) {
            $fieldId = $metafieldIds[$code] ?? null;

            if (! $fieldId) {
                Log::warning('Metafield ID not configured', [
                    'code' => $code,
                    'environment' => $this->getEnvironment(),
                ]);

                continue;
            }

            $setMetafields[] = [
                'field' => (int) $fieldId,
                'value' => $value,
            ];
        }

        if (empty($setMetafields)) {
            return;
        }

        $this->request('PUT', "model/party.party/{$partyId}", [
            'json' => [
                'metafields' => [
                    'set' => $setMetafields,
                ],
            ],
        ]);

        Log::info('Updated contact metafields', [
            'party_id' => $partyId,
            'metafields' => array_keys($values),
        ]);
    }

    /**
     * Get AR automation settings for a customer.
     *
     * Returns an array with:
     * - edi: bool
     * - consolidated_invoicing: bool
     * - requires_customer_skus: bool
     * - invoice_discount: float|null
     */
    public function getCustomerArSettings(int $partyId): array
    {
        $metafields = $this->getContactMetafields($partyId);

        return [
            'edi' => (bool) ($metafields['edi'] ?? false),
            'consolidated_invoicing' => filter_var($metafields['consolidated_invoicing'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'requires_customer_skus' => (bool) ($metafields['requires_customer_skus'] ?? false),
            'invoice_discount' => isset($metafields['invoice_discount'])
                ? (float) $metafields['invoice_discount']
                : null,
        ];
    }

    /**
     * Update AR automation settings for a customer.
     *
     * @param  int  $partyId  The Fulfil party ID
     * @param  array  $settings  Array with keys: edi, consolidated_invoicing, requires_customer_skus, invoice_discount
     */
    public function updateCustomerArSettings(int $partyId, array $settings): void
    {
        $values = [];

        if (array_key_exists('edi', $settings)) {
            $values['edi'] = $settings['edi'] ? 'true' : 'false';
        }

        if (array_key_exists('consolidated_invoicing', $settings)) {
            $values['consolidated_invoicing'] = $settings['consolidated_invoicing'] ? 'true' : 'false';
        }

        if (array_key_exists('requires_customer_skus', $settings)) {
            $values['requires_customer_skus'] = $settings['requires_customer_skus'] ? 'true' : 'false';
        }

        if (array_key_exists('invoice_discount', $settings) && $settings['invoice_discount'] !== null && $settings['invoice_discount'] !== '') {
            $values['invoice_discount'] = (string) $settings['invoice_discount'];
        }

        if (! empty($values)) {
            $this->updateContactMetafields($partyId, $values);
        }
    }
}
