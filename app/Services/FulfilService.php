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
                    $parsed['logistics'][] = ['name' => $contactName, 'email' => $value];
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
        if (!$templateId) {
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
        $cacheKey = 'template_id_' . md5($name);

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

    protected function fetchProductTemplates(array $ids): array
    {
        if (empty($ids)) return [];

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
                ->filter(fn($pl) => str_contains(strtolower($pl['name'] ?? ''), 'wholesale'))
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
                ->map(fn($cat) => [
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
     * @param array $data Customer data with keys:
     *   - name: string (required)
     *   - sale_price_list: int (required) - price list ID
     *   - customer_payment_term: int (required) - payment term ID
     *   - shipping_terms_category_id: int (required) - category ID for Pickup/Delivered
     *   - shelf_life_requirement: int (required) - days
     *   - vendor_guide: string|null (optional) - URL
     *   - buyers: array (required, min 1) - [{name, email}, ...]
     *   - accounts_payable: array (optional) - [{name, value}, ...] where value is email or URL
     *   - logistics: array (optional) - [{name, email}, ...]
     *
     * @return array The created customer with ID
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

        $partyResponse = $this->request('POST', 'model/party.party', [
            'json' => [$partyData],
        ]);

        // API returns array of created records: [['id' => 123, 'rec_name' => 'Name'], ...]
        $partyId = $partyResponse[0]['id'] ?? null;
        if (!$partyId) {
            throw new \RuntimeException('Failed to create customer: no ID returned');
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
                'name' => 'Buyer: ' . $buyer['name'],
                'value' => $buyer['email'],
            ];
        }

        // Accounts Payable (optional, can be email or URL)
        foreach ($data['accounts_payable'] ?? [] as $ap) {
            $isUrl = str_starts_with($ap['value'], 'http://') || str_starts_with($ap['value'], 'https://');
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => $isUrl ? 'website' : 'email',
                'name' => 'Accounts Payable: ' . $ap['name'],
                'value' => $ap['value'],
            ];
        }

        // Logistics (optional)
        foreach ($data['logistics'] ?? [] as $logistics) {
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => 'Logistics: ' . $logistics['name'],
                'value' => $logistics['email'],
            ];
        }

        // Shelf life requirement (required)
        // Using type 'email' so it's exposed to data warehouse
        $contactMechanisms[] = [
            'party' => $partyId,
            'type' => 'email',
            'name' => 'data',
            'value' => 'shelf_life_req:' . $data['shelf_life_requirement'],
        ];

        // Vendor guide (optional)
        // Using type 'email' so it's exposed to data warehouse
        if (!empty($data['vendor_guide'])) {
            $contactMechanisms[] = [
                'party' => $partyId,
                'type' => 'email',
                'name' => 'data',
                'value' => 'vendor_guide:' . $data['vendor_guide'],
            ];
        }

        // Batch create all contact mechanisms
        if (!empty($contactMechanisms)) {
            $this->request('POST', 'model/party.contact_mechanism', [
                'json' => $contactMechanisms,
            ]);
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
     * @param int $partyId The customer's party ID
     * @param array $data Customer data (same structure as createCustomer)
     * @return array The updated customer
     */
    public function updateCustomer(int $partyId, array $data): array
    {
        // 1. Update the party record
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

        if (!empty($partyData)) {
            $this->request('PUT', "model/party.party/{$partyId}", [
                'json' => $partyData,
            ]);
        }

        // 2. Update shipping terms category if provided
        if (isset($data['shipping_terms_category_id'])) {
            $this->updateShippingTermsCategory($partyId, $data['shipping_terms_category_id']);
        }

        // 3. Update contact mechanisms if provided
        // For updates, we need to fetch existing mechanisms, compare, and update/create/delete
        if ($this->hasContactMechanismUpdates($data)) {
            $this->syncContactMechanisms($partyId, $data);
        }

        // Clear the active customers cache
        $this->clearCache('active_customers');

        return [
            'id' => $partyId,
            'name' => $data['name'] ?? null,
        ];
    }

    /**
     * Add a category to a party.
     */
    protected function addCategoryToParty(int $partyId, int $categoryId): void
    {
        // Fulfil uses a write operation to add categories
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
        if (!in_array($newCategoryId, $currentCategories)) {
            $operations[] = ['add', [$newCategoryId]];
        }

        if (!empty($operations)) {
            $this->request('PUT', "model/party.party/{$partyId}", [
                'json' => [
                    'categories' => $operations,
                ],
            ]);
        }
    }

    /**
     * Check if data contains contact mechanism updates.
     */
    protected function hasContactMechanismUpdates(array $data): bool
    {
        return isset($data['buyers'])
            || isset($data['accounts_payable'])
            || isset($data['logistics'])
            || isset($data['shelf_life_requirement'])
            || isset($data['vendor_guide']);
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
                if (str_starts_with($mech['name'] ?? '', $namePrefix) && !in_array($mech['id'], $matchedIds)) {
                    return $mech;
                }
            }
            return null;
        };

        // Process buyers
        if (isset($data['buyers'])) {
            // Mark all existing buyer mechanisms for potential deletion
            $existingBuyers = array_filter($existing, fn($m) => str_starts_with($m['name'] ?? '', 'Buyer:'));

            foreach ($data['buyers'] as $buyer) {
                $mechName = 'Buyer: ' . $buyer['name'];
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
                if (!in_array($eb['id'], $matchedIds)) {
                    $toDelete[] = $eb['id'];
                }
            }
        }

        // Process accounts payable
        if (isset($data['accounts_payable'])) {
            $existingAP = array_filter($existing, fn($m) => str_starts_with($m['name'] ?? '', 'Accounts Payable:'));

            foreach ($data['accounts_payable'] as $ap) {
                $mechName = 'Accounts Payable: ' . $ap['name'];
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
                if (!in_array($eap['id'], $matchedIds)) {
                    $toDelete[] = $eap['id'];
                }
            }
        }

        // Process logistics
        if (isset($data['logistics'])) {
            $existingLogistics = array_filter($existing, fn($m) => str_starts_with($m['name'] ?? '', 'Logistics:'));

            foreach ($data['logistics'] as $logistics) {
                $mechName = 'Logistics: ' . $logistics['name'];
                $found = collect($existingLogistics)->firstWhere('name', $mechName);

                if ($found) {
                    $matchedIds[] = $found['id'];
                    if ($found['value'] !== $logistics['email']) {
                        $toUpdate[] = ['id' => $found['id'], 'value' => $logistics['email']];
                    }
                } else {
                    $toCreate[] = [
                        'party' => $partyId,
                        'type' => 'email',
                        'name' => $mechName,
                        'value' => $logistics['email'],
                    ];
                }
            }

            foreach ($existingLogistics as $el) {
                if (!in_array($el['id'], $matchedIds)) {
                    $toDelete[] = $el['id'];
                }
            }
        }

        // Process shelf life requirement
        if (isset($data['shelf_life_requirement'])) {
            $existingShelfLife = collect($existing)->first(fn($m) => $m['name'] === 'data' && str_starts_with($m['value'] ?? '', 'shelf_life_req:'));

            if ($existingShelfLife) {
                $matchedIds[] = $existingShelfLife['id'];
                $newValue = 'shelf_life_req:' . $data['shelf_life_requirement'];
                if ($existingShelfLife['value'] !== $newValue) {
                    $toUpdate[] = ['id' => $existingShelfLife['id'], 'value' => $newValue];
                }
            } else {
                // Using type 'email' so it's exposed to data warehouse
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'shelf_life_req:' . $data['shelf_life_requirement'],
                ];
            }
        }

        // Process vendor guide
        if (array_key_exists('vendor_guide', $data)) {
            $existingVendorGuide = collect($existing)->first(fn($m) => $m['name'] === 'data' && str_starts_with($m['value'] ?? '', 'vendor_guide:'));

            if ($existingVendorGuide) {
                $matchedIds[] = $existingVendorGuide['id'];
                if (empty($data['vendor_guide'])) {
                    // Delete if cleared
                    $toDelete[] = $existingVendorGuide['id'];
                } else {
                    $newValue = 'vendor_guide:' . $data['vendor_guide'];
                    if ($existingVendorGuide['value'] !== $newValue) {
                        $toUpdate[] = ['id' => $existingVendorGuide['id'], 'value' => $newValue];
                    }
                }
            } elseif (!empty($data['vendor_guide'])) {
                // Using type 'email' so it's exposed to data warehouse
                $toCreate[] = [
                    'party' => $partyId,
                    'type' => 'email',
                    'name' => 'data',
                    'value' => 'vendor_guide:' . $data['vendor_guide'],
                ];
            }
        }

        // Execute operations
        if (!empty($toCreate)) {
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

        if (!empty($toDelete)) {
            $this->request('DELETE', 'model/party.contact_mechanism', [
                'json' => $toDelete,
            ]);
        }
    }
}
