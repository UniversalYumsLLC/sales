<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FulfilService
{
    protected string $baseUrl;
    protected string $token;
    protected int $cacheTtl;
    protected string $cachePrefix;
    protected int $maxRetries;

    public function __construct(?string $environment = null)
    {
        $env = $environment ?? config('fulfil.default');
        $config = config("fulfil.environments.{$env}");

        if (!$config['subdomain'] || !$config['token']) {
            throw new \RuntimeException("Fulfil {$env} environment not configured");
        }

        $this->baseUrl = "https://{$config['subdomain']}.fulfil.io/api/v2";
        $this->token = $config['token'];
        $this->cacheTtl = config('fulfil.cache.ttl', 3600);
        $this->cachePrefix = config('fulfil.cache.prefix', 'fulfil_');
        $this->maxRetries = config('fulfil.rate_limit.max_retries', 3);
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

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-KEY' => $this->token,
                    'Content-Type' => 'application/json',
                ])->{$method}($url, $options['json'] ?? $options['query'] ?? []);

            if ($response->successful()) {
                return $response->json();
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
                if (!$isValidationError) {
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

            $lastException = new \RuntimeException("Fulfil API error: {$response->status()}");
            Log::error('Fulfil API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'attempt' => $attempt,
            ]);

            // For non-429 errors, don't retry
            if ($response->status() !== 429) {
                throw $lastException;
            }
        }

        throw $lastException ?? new \RuntimeException("Fulfil API error: max retries exceeded");
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
        $cacheKey = $this->cachePrefix . $key;
        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear cache for a specific key or all Fulfil cache
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($this->cachePrefix . $key);
        } else {
            // Clear all Fulfil cache by tag or pattern
            Cache::flush(); // Simple approach - in production, use tags
        }
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
            'buyers' => [],
            'accounts_payable' => [],
            'logistics' => [],
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
            if (!isset($mechanisms[$mechanismId])) continue;
            $mechanism = $mechanisms[$mechanismId];
            $this->parseContactMechanism($mechanism, $parsed);
        }

        // Parse categories for shipping terms
        foreach ($contact['categories'] ?? [] as $categoryId) {
            if (!isset($categories[$categoryId])) continue;
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

        // Department contact: name = "Accounts Payable: Contact Name"
        if (str_contains($name, ':')) {
            [$department, $contactName] = explode(':', $name, 2);
            $department = trim(strtolower($department));
            $contactName = trim($contactName);

            // Only process if value looks like an email
            if (str_contains($value, '@') && str_contains($value, '.')) {
                $contactData = ['name' => $contactName, 'email' => $value];

                if (str_contains($department, 'buyer')) {
                    $parsed['buyers'][] = $contactData;
                } elseif (str_contains($department, 'accounts payable') || str_contains($department, 'ap')) {
                    $parsed['accounts_payable'][] = $contactData;
                } elseif (str_contains($department, 'logistics')) {
                    $parsed['logistics'][] = $contactData;
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
        $cacheKey = 'sales_orders_' . md5(json_encode($filters));

        if ($bustCache) {
            $this->clearCache($cacheKey);
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
            $baseFilters = array_filter($baseFilters, fn($f) => $f[0] !== 'state');
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
        $cacheKey = 'invoices_' . md5(json_encode($filters));

        if ($bustCache) {
            $this->clearCache($cacheKey);
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
        ];

        // Note: Fulfil API max page size is 500
        $response = $this->request('PUT', 'model/account.invoice/search_read', [
            'json' => [
                'filters' => $baseFilters,
                'fields' => $fields,
                'limit' => 500,
                'order' => [['invoice_date', 'DESC']],
            ],
        ]);

        // Fetch payment terms
        $paymentTermIds = array_unique(array_filter(array_column($response, 'payment_term')));
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
                'payment_terms' => isset($paymentTermsById[$invoice['payment_term'] ?? null])
                    ? $paymentTermsById[$invoice['payment_term']]['name']
                    : null,
                'sales_order_ids' => $invoice['sales'] ?? [],
            ];
        }, $response);
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
     */
    protected function fetchProducts(): array
    {
        $fields = [
            'id', 'code', 'rec_name', 'template',
            'wholesale_list_price', 'active', 'attributes',
        ];

        // Note: Fulfil API max page size is 500
        $response = $this->request('PUT', 'model/product.product/search_read', [
            'json' => [
                'filters' => [['active', '=', true]],
                'fields' => $fields,
                'limit' => 500,
            ],
        ]);

        return $response;
    }

    /**
     * Enrich products with attributes
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

        return array_map(function ($product) use ($attributesById, $classAttrId, $startDateAttrId, $endDateAttrId, $seasonAttrId) {
            $parsed = [
                'id' => $product['id'],
                'sku' => $product['code'] ?? null,
                'name' => $product['rec_name'] ?? null,
                'template_id' => $product['template'] ?? null,
                'wholesale_list_price' => $this->parseDecimal($product['wholesale_list_price'] ?? null),
                'class' => null,
                'start_date' => null,
                'discontinued_date' => null,
                'season' => null,
            ];

            foreach ($product['attributes'] ?? [] as $attrId) {
                if (!isset($attributesById[$attrId])) continue;
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
        if (empty($ids)) return [];

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
        if (empty($ids)) return [];

        return $this->batchFetchByIds('model/party.contact_mechanism', $ids, 'id,type,value,name,party,active');
    }

    protected function fetchCategories(array $ids): array
    {
        if (empty($ids)) return [];

        return $this->batchFetchByIds('model/party.category', $ids, 'id,name,parent,rec_name');
    }

    protected function fetchPriceLists(array $ids): array
    {
        if (empty($ids)) return [];

        return $this->batchFetchByIds('model/product.price_list', $ids, 'id,name');
    }

    protected function fetchPaymentTerms(array $ids): array
    {
        if (empty($ids)) return [];

        return $this->batchFetchByIds('model/account.invoice.payment_term', $ids, 'id,name');
    }

    protected function fetchSalesOrderLines(array $ids): array
    {
        if (empty($ids)) return [];

        // Batch IDs to avoid URI Too Long (414) errors
        return $this->batchFetchByIds('model/sale.line', $ids, 'id,product,description,quantity,unit_price,amount,rec_name');
    }

    protected function fetchShipments(array $ids): array
    {
        if (empty($ids)) return [];

        // Fetch customer shipments with effective_date for done order date calculations
        return $this->batchFetchByIds('model/customer_shipment', $ids, 'id,effective_date,state');
    }

    protected function fetchProductAttributes(array $ids): array
    {
        if (empty($ids)) return [];

        return $this->batchFetchByIds('model/product.product.attribute', $ids, 'id,attribute,value,value_selection');
    }
}
