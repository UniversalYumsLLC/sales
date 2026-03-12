<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\FulfilBrokerContact;
use App\Models\FulfilContactMetadata;
use App\Models\FulfilCustomerMetadata;
use App\Models\FulfilUncategorizedContact;
use App\Services\FulfilService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ActiveCustomersController extends Controller
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Get the effective date for revenue calculations based on order state.
     *
     * For done orders: Use shipment effective_date (when goods were actually shipped)
     * For confirmed/processing orders: Use shipping_end_date (expected ship date)
     * Fallback: sale_date if neither is available
     */
    protected function getEffectiveDate(array $order): ?Carbon
    {
        $state = $order['state'] ?? null;

        if ($state === 'done') {
            // For done orders, use shipment effective_date
            $dateStr = $order['shipment_effective_date'] ?? $order['sale_date'] ?? null;
        } else {
            // For confirmed/processing, use shipping_end_date
            $dateStr = $order['shipping_end_date'] ?? $order['sale_date'] ?? null;
        }

        return $dateStr ? Carbon::parse($dateStr) : null;
    }

    /**
     * Display the active customers list
     */
    public function index(Request $request)
    {
        $bustCache = $request->boolean('refresh');
        $search = $request->string('search')->trim();

        $customers = $this->getActiveCustomersWithMetrics($bustCache);

        // Filter by search term
        if ($search->isNotEmpty()) {
            $searchLower = strtolower($search);
            $customers = array_filter($customers, function ($customer) use ($searchLower) {
                return str_contains(strtolower($customer['name']), $searchLower);
            });
            $customers = array_values($customers);
        }

        // Calculate summary totals
        $totals = [
            'total_customers' => count($customers),
            'open_po_revenue' => array_sum(array_column($customers, 'open_po_total')),
            't12m_revenue' => array_sum(array_column($customers, 't12m_revenue')),
        ];

        return Inertia::render('ActiveCustomers/Index', [
            'customers' => $customers,
            'totals' => $totals,
            'search' => (string) $search,
            'lastUpdated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Display customer details
     */
    public function show(Request $request, int $id)
    {
        $bustCache = $request->boolean('refresh');

        $customers = $this->getActiveCustomersWithMetrics($bustCache);
        $customer = collect($customers)->firstWhere('id', $id);

        if (!$customer) {
            abort(404, 'Customer not found');
        }

        // Get detailed data for this customer
        $salesOrders = $this->fulfil->getSalesOrders(['party_id' => $id], $bustCache);
        $invoices = $this->fulfil->getInvoices(['party_id' => $id], $bustCache);

        // Get payment terms from invoices as fallback if customer doesn't have one set
        if (empty($customer['payment_terms'])) {
            $invoiceWithPaymentTerms = collect($invoices)->firstWhere('payment_terms', '!=', null);
            if ($invoiceWithPaymentTerms) {
                $customer['payment_terms'] = $invoiceWithPaymentTerms['payment_terms'];
            }
        }

        // Merge in local metadata (company_urls for Gmail matching, broker info)
        $localMetadata = FulfilCustomerMetadata::find($id);
        $customer['company_urls'] = $localMetadata?->company_urls ?? [];
        $customer['broker'] = $localMetadata?->broker ?? false;
        $customer['broker_commission'] = $localMetadata?->broker_commission;
        $customer['broker_company_name'] = $localMetadata?->broker_company_name;

        // Get broker contacts
        $brokerContacts = FulfilBrokerContact::where('fulfil_party_id', $id)->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
            'last_received_at' => $c->last_received_at?->toIso8601String(),
        ])->toArray();

        // Merge email tracking dates into buyer contacts
        $buyerContacts = $this->mergeContactEmailMetadata($id, $customer['buyers'] ?? []);

        // Get local contacts (discovered from emails)
        $localContacts = FulfilUncategorizedContact::where('fulfil_party_id', $id)->get();

        // Separate uncategorized from categorized local contacts
        $uncategorizedContacts = $localContacts->whereNull('type')->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
            'last_received_at' => $c->last_received_at?->toIso8601String(),
        ])->values()->toArray();

        // Merge categorized local contacts with Fulfil contacts
        $localBuyers = $localContacts->where('type', 'buyer')->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'is_local' => true,
            'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
            'last_received_at' => $c->last_received_at?->toIso8601String(),
        ])->values()->toArray();

        $localAP = $localContacts->where('type', 'accounts_payable')->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'is_local' => true,
        ])->values()->toArray();

        $localLogistics = $localContacts->where('type', 'logistics')->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'is_local' => true,
        ])->values()->toArray();

        // Calculate T12M monthly revenue
        $monthlyRevenue = $this->calculateMonthlyRevenue($salesOrders);

        // Get top products by revenue
        $topProducts = $this->calculateTopProducts($salesOrders);

        // Get upcoming orders (confirmed/processing)
        $upcomingOrders = $this->getUpcomingOrders($salesOrders);

        // Get outstanding invoices
        $outstandingInvoices = $this->getOutstandingInvoices($invoices);

        return Inertia::render('ActiveCustomers/Show', [
            'customer' => $customer,
            'buyerContacts' => $buyerContacts,
            'brokerContacts' => $brokerContacts,
            'localBuyers' => $localBuyers,
            'localAP' => $localAP,
            'localLogistics' => $localLogistics,
            'uncategorizedContacts' => $uncategorizedContacts,
            'monthlyRevenue' => $monthlyRevenue,
            'topProducts' => $topProducts,
            'upcomingOrders' => $upcomingOrders,
            'outstandingInvoices' => $outstandingInvoices,
            'lastUpdated' => now()->toIso8601String(),
            // Form options for editing
            'priceLists' => $this->fulfil->getAllPriceLists($bustCache),
            'paymentTerms' => $this->fulfil->getAllPaymentTerms($bustCache),
            'shippingTerms' => $this->fulfil->getShippingTermsCategories($bustCache),
        ]);
    }

    /**
     * Get active customers with calculated metrics
     */
    protected function getActiveCustomersWithMetrics(bool $bustCache = false): array
    {
        $customers = $this->fulfil->getActiveCustomers($bustCache);

        // Fetch done orders from last 2 years (for T12M and prior year comparison)
        $t12mStart = Carbon::now()->subYear();
        $t24mStart = Carbon::now()->subYears(2);
        $doneOrders = $this->fulfil->getSalesOrders([
            'state' => ['done'],
            'shipping_date_from' => $t24mStart->toDateString(),
        ], $bustCache);

        // Fetch open orders separately (confirmed/processing) - these need no date filter
        $openOrders = $this->fulfil->getSalesOrders([
            'state' => ['confirmed', 'processing'],
        ], $bustCache);

        // Merge both sets of orders
        $allOrders = array_merge($doneOrders, $openOrders);

        $allInvoices = $this->fulfil->getInvoices(['state' => ['validated', 'posted']], $bustCache);

        // Group orders and invoices by party_id
        $ordersByParty = collect($allOrders)->groupBy('party_id');
        $invoicesByParty = collect($allInvoices)->groupBy('party_id');

        // Calculate metrics for each customer
        $today = Carbon::today();

        $mappedCustomers = array_map(function ($customer) use ($ordersByParty, $invoicesByParty, $t12mStart, $t24mStart, $today) {
            $partyOrders = $ordersByParty->get($customer['id'], collect());
            $partyInvoices = $invoicesByParty->get($customer['id'], collect());

            // Check if active customer (has done order or has open order)
            $hasDoneOrder = $partyOrders->where('state', 'done')->isNotEmpty();
            $hasOpenOrder = $partyOrders->whereIn('state', ['confirmed', 'processing'])->isNotEmpty();

            if (!$hasDoneOrder && !$hasOpenOrder) {
                return null; // Not an active customer
            }

            // Open POs (confirmed or processing)
            $openOrders = $partyOrders->whereIn('state', ['confirmed', 'processing']);
            $openPoCount = $openOrders->count();
            $openPoTotal = $openOrders->sum('total_amount');

            // T12M Revenue (done orders in last year)
            // Uses shipping_end_date if available, falls back to sale_date
            $t12mOrders = $partyOrders
                ->where('state', 'done')
                ->filter(function ($order) use ($t12mStart) {
                    $effectiveDate = $this->getEffectiveDate($order);
                    if (!$effectiveDate) return false;
                    return $effectiveDate->gte($t12mStart);
                });
            $t12mRevenue = $t12mOrders->sum('total_amount');

            // Prior Year Revenue (months 13-24, same period one year earlier)
            $priorYearOrders = $partyOrders
                ->where('state', 'done')
                ->filter(function ($order) use ($t12mStart, $t24mStart) {
                    $effectiveDate = $this->getEffectiveDate($order);
                    if (!$effectiveDate) return false;
                    return $effectiveDate->gte($t24mStart) && $effectiveDate->lt($t12mStart);
                });
            $priorYearRevenue = $priorYearOrders->sum('total_amount');

            // Calculate overdue invoices
            $overdueInvoices = $partyInvoices
                ->where('balance', '>', 0)
                ->filter(function ($invoice) use ($today) {
                    if (!$invoice['due_date']) return false;
                    $dueDate = Carbon::parse($invoice['due_date'])->startOfDay();
                    // Invoice is overdue if due date is before today
                    return $dueDate->isBefore($today);
                });
            $overdueCount = $overdueInvoices->count();
            $overdueTotal = $overdueInvoices->sum('balance');

            // Calculate late shipments (open orders with shipping_end_date in the past)
            $lateShipments = $openOrders->filter(function ($order) use ($today) {
                if (!$order['shipping_end_date']) return false;
                $shipDate = Carbon::parse($order['shipping_end_date'])->startOfDay();
                return $shipDate->isBefore($today);
            });
            $lateShipmentsCount = $lateShipments->count();

            return array_merge($customer, [
                'open_po_count' => $openPoCount,
                'open_po_total' => $openPoTotal,
                't12m_revenue' => $t12mRevenue,
                'prior_year_revenue' => $priorYearRevenue,
                'revenue_change' => $t12mRevenue - $priorYearRevenue,
                'overdue_count' => $overdueCount,
                'overdue_total' => $overdueTotal,
                'late_shipments_count' => $lateShipmentsCount,
                'is_active' => true,
            ]);
        }, $customers);

        // Filter out non-active and null entries
        $activeCustomers = array_values(array_filter($mappedCustomers, fn($c) => $c !== null));

        // Sort by T12M revenue from high to low
        usort($activeCustomers, fn($a, $b) => $b['t12m_revenue'] <=> $a['t12m_revenue']);

        return $activeCustomers;
    }

    /**
     * Calculate monthly revenue for T12M chart with YoY comparison
     */
    protected function calculateMonthlyRevenue(array $salesOrders): array
    {
        $t12mStart = Carbon::now()->subYear()->startOfMonth();
        $t24mStart = Carbon::now()->subYears(2)->startOfMonth();
        $months = [];

        // Initialize all months with current and prior year data
        for ($i = 0; $i < 12; $i++) {
            $currentMonth = $t12mStart->copy()->addMonths($i);
            $priorMonth = $t24mStart->copy()->addMonths($i);
            $months[$currentMonth->format('Y-m')] = [
                'month' => $currentMonth->format('M Y'),
                'month_name' => $currentMonth->format('M'),
                'revenue' => 0,
                'prior_year_month' => $priorMonth->format('M Y'),
                'prior_year_revenue' => 0,
            ];
        }

        // Sum revenue by month for done orders
        // Uses shipping_end_date if available, falls back to sale_date
        foreach ($salesOrders as $order) {
            if ($order['state'] !== 'done') continue;

            $date = $this->getEffectiveDate($order);
            if (!$date) continue;

            // Check if it falls in current T12M period
            $key = $date->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['revenue'] += $order['total_amount'] ?? 0;
            }

            // Check if it falls in prior year period (months 13-24)
            // Map to corresponding current year month
            if ($date->gte($t24mStart) && $date->lt($t12mStart)) {
                $correspondingCurrentMonth = $date->copy()->addYear()->format('Y-m');
                if (isset($months[$correspondingCurrentMonth])) {
                    $months[$correspondingCurrentMonth]['prior_year_revenue'] += $order['total_amount'] ?? 0;
                }
            }
        }

        return array_values($months);
    }

    /**
     * Calculate top products by revenue
     */
    protected function calculateTopProducts(array $salesOrders): array
    {
        $t12mStart = Carbon::now()->subYear();
        $productStats = [];

        foreach ($salesOrders as $order) {
            if ($order['state'] !== 'done') continue;

            $date = $this->getEffectiveDate($order);
            if (!$date || $date->lt($t12mStart)) continue;

            foreach ($order['lines'] ?? [] as $line) {
                $sku = $line['sku'] ?? 'Unknown';
                if (!isset($productStats[$sku])) {
                    $productStats[$sku] = [
                        'sku' => $sku,
                        'name' => $line['description'] ?? $sku,
                        'units_sold' => 0,
                        'revenue' => 0,
                    ];
                }
                $productStats[$sku]['units_sold'] += $line['quantity'] ?? 0;
                $productStats[$sku]['revenue'] += $line['amount'] ?? 0;
            }
        }

        // Sort by revenue and take top 10
        $sorted = collect($productStats)->sortByDesc('revenue')->take(10)->values()->toArray();

        return $sorted;
    }

    /**
     * Get upcoming orders (confirmed/processing)
     */
    protected function getUpcomingOrders(array $salesOrders): array
    {
        $upcoming = collect($salesOrders)
            ->whereIn('state', ['confirmed', 'processing'])
            ->sortBy('shipping_end_date')
            ->values()
            ->map(function ($order) {
                return [
                    'id' => $order['id'],
                    'reference' => $order['reference'], // Customer PO
                    'sale_date' => $order['sale_date'],
                    'shipping_end_date' => $order['shipping_end_date'],
                    'total_amount' => $order['total_amount'],
                    'lines' => $order['lines'],
                ];
            })
            ->toArray();

        return $upcoming;
    }

    /**
     * Get outstanding invoices
     */
    protected function getOutstandingInvoices(array $invoices): array
    {
        $today = Carbon::today();

        return collect($invoices)
            ->whereIn('state', ['validated', 'posted'])
            ->where('balance', '>', 0)
            ->map(function ($invoice) use ($today) {
                $daysOverdue = 0;

                if ($invoice['due_date']) {
                    $dueDate = Carbon::parse($invoice['due_date'])->startOfDay();

                    // Calculate difference: positive = overdue, negative = not yet due
                    // $dueDate->diffInDays($today, false) gives:
                    //   - positive when today is AFTER dueDate (invoice is overdue)
                    //   - negative when today is BEFORE dueDate (invoice not yet due)
                    $daysOverdue = (int) $dueDate->diffInDays($today, false);
                }

                return [
                    'id' => $invoice['id'],
                    'number' => $invoice['number'],
                    'total_amount' => $invoice['total_amount'],
                    'balance' => $invoice['balance'],
                    'due_date' => $invoice['due_date'],
                    'days_overdue' => $daysOverdue,
                    'sales_order_ids' => $invoice['sales_order_ids'],
                ];
            })
            ->sortByDesc('days_overdue')
            ->values()
            ->toArray();
    }

    /**
     * Merge email tracking metadata into buyer contacts
     *
     * Buyer contacts from Fulfil have structure: ['name' => 'John Doe', 'email' => 'john@example.com']
     * This merges in last_emailed_at and last_received_at from local metadata.
     */
    protected function mergeContactEmailMetadata(int $partyId, array $contacts): array
    {
        if (empty($contacts)) {
            return [];
        }

        // Get all contact metadata for this customer
        $metadata = FulfilContactMetadata::where('fulfil_party_id', $partyId)
            ->get()
            ->keyBy(fn($m) => strtolower($m->email));

        return array_map(function ($contact) use ($metadata) {
            $email = strtolower($contact['email'] ?? '');
            $contactMetadata = $metadata->get($email);

            return [
                'name' => $contact['name'] ?? '',
                'email' => $contact['email'] ?? '',
                'last_emailed_at' => $contactMetadata?->last_emailed_at?->toIso8601String(),
                'last_received_at' => $contactMetadata?->last_received_at?->toIso8601String(),
            ];
        }, $contacts);
    }

    /**
     * Update company URLs for Gmail domain matching
     */
    public function updateCompanyUrls(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'company_urls' => 'required|array',
            'company_urls.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        // Normalize URLs (extract domains, lowercase)
        $urls = array_values(array_filter(array_map(function ($url) {
            $url = trim($url);
            if (empty($url)) return null;

            // Extract domain from URL if it looks like a full URL
            if (preg_match('/^https?:\/\//', $url)) {
                $parsed = parse_url($url);
                $url = $parsed['host'] ?? $url;
            }

            // Remove www. prefix
            $url = preg_replace('/^www\./', '', strtolower($url));

            return $url;
        }, $request->company_urls)));

        // Update or create metadata
        $metadata = FulfilCustomerMetadata::findOrCreateForCustomer($id);
        $metadata->company_urls = $urls;
        $metadata->save();

        return back()->with('success', 'Company URLs updated successfully.');
    }

    /**
     * Create a new local contact for a customer (uncategorized by default).
     */
    public function createLocalContact(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'type' => ['nullable', 'in:buyer,accounts_payable,logistics'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ensure customer metadata exists
        FulfilCustomerMetadata::findOrCreateForCustomer($id);

        // Check if contact with this email already exists
        $existing = FulfilUncategorizedContact::where('fulfil_party_id', $id)
            ->whereRaw('LOWER(email) = ?', [strtolower($request->email)])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A contact with this email already exists',
            ], 422);
        }

        $contact = FulfilUncategorizedContact::create([
            'fulfil_party_id' => $id,
            'name' => $request->name,
            'email' => strtolower($request->email),
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Contact created successfully',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'type' => $contact->type,
            ],
        ]);
    }

    /**
     * Update a local contact for a customer.
     */
    public function updateLocalContact(Request $request, int $customerId, int $contactId): JsonResponse
    {
        $contact = FulfilUncategorizedContact::where('fulfil_party_id', $customerId)
            ->where('id', $contactId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('email')) {
            $updateData['email'] = strtolower($request->email);
        }

        if (!empty($updateData)) {
            $contact->update($updateData);
        }

        return response()->json([
            'message' => 'Contact updated successfully',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'type' => $contact->type,
            ],
        ]);
    }

    /**
     * Delete a local contact for a customer.
     */
    public function deleteLocalContact(int $customerId, int $contactId): JsonResponse
    {
        $contact = FulfilUncategorizedContact::where('fulfil_party_id', $customerId)
            ->where('id', $contactId)
            ->firstOrFail();

        $contact->delete();

        return response()->json([
            'message' => 'Contact deleted successfully',
        ]);
    }

    /**
     * Categorize an uncategorized local contact.
     */
    public function categorizeContact(Request $request, int $customerId, int $contactId): JsonResponse
    {
        $contact = FulfilUncategorizedContact::where('fulfil_party_id', $customerId)
            ->where('id', $contactId)
            ->firstOrFail();

        // Only allow categorization of uncategorized contacts (type = null)
        if ($contact->type !== null) {
            return response()->json([
                'message' => 'Only uncategorized contacts can be categorized',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:buyer,accounts_payable,logistics'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid contact type',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact->update(['type' => $request->type]);

        return response()->json([
            'message' => 'Contact categorized successfully',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'type' => $contact->type,
            ],
        ]);
    }

    /**
     * Update broker settings for a customer.
     */
    public function updateBroker(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'broker' => ['required', 'boolean'],
            'broker_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'broker_company_name' => ['nullable', 'string', 'max:255'],
            'broker_contacts' => ['nullable', 'array'],
            'broker_contacts.*.name' => ['required', 'string', 'min:1', 'max:100'],
            'broker_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $metadata = FulfilCustomerMetadata::findOrCreateForCustomer($id);
        $metadata->broker = $request->broker;
        $metadata->broker_commission = $request->broker_commission;
        $metadata->broker_company_name = $request->broker_company_name;
        $metadata->save();

        // Update broker contacts if provided
        if ($request->has('broker_contacts')) {
            // Delete existing broker contacts for this customer
            FulfilBrokerContact::where('fulfil_party_id', $id)->delete();

            // Create new broker contacts
            $contacts = $request->broker_contacts ?? [];
            foreach ($contacts as $contactData) {
                if (!empty($contactData['name'])) {
                    FulfilBrokerContact::create([
                        'fulfil_party_id' => $id,
                        'name' => $contactData['name'],
                        'email' => strtolower($contactData['email'] ?? ''),
                    ]);
                }
            }
        }

        // TODO: Sync to Fulfil metafields

        return response()->json([
            'message' => 'Broker settings updated successfully',
            'broker' => $metadata->broker,
            'broker_commission' => $metadata->broker_commission,
        ]);
    }

    /**
     * Create a broker contact for a customer.
     */
    public function createBrokerContact(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Ensure customer metadata exists
        FulfilCustomerMetadata::findOrCreateForCustomer($id);

        // Check if contact with this email already exists
        $existing = FulfilBrokerContact::where('fulfil_party_id', $id)
            ->whereRaw('LOWER(email) = ?', [strtolower($request->email)])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A broker contact with this email already exists',
            ], 422);
        }

        $contact = FulfilBrokerContact::create([
            'fulfil_party_id' => $id,
            'name' => $request->name,
            'email' => strtolower($request->email),
        ]);

        // TODO: Sync to Fulfil contacts with "Broker: Name" format

        return response()->json([
            'message' => 'Broker contact created successfully',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Update a broker contact for a customer.
     */
    public function updateBrokerContact(Request $request, int $customerId, int $contactId): JsonResponse
    {
        $contact = FulfilBrokerContact::where('fulfil_party_id', $customerId)
            ->where('id', $contactId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:1', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('email')) {
            $updateData['email'] = strtolower($request->email);
        }

        if (!empty($updateData)) {
            $contact->update($updateData);
        }

        // TODO: Sync to Fulfil contacts

        return response()->json([
            'message' => 'Broker contact updated successfully',
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Delete a broker contact for a customer.
     */
    public function deleteBrokerContact(int $customerId, int $contactId): JsonResponse
    {
        $contact = FulfilBrokerContact::where('fulfil_party_id', $customerId)
            ->where('id', $contactId)
            ->firstOrFail();

        $contact->delete();

        // TODO: Remove from Fulfil contacts

        return response()->json([
            'message' => 'Broker contact deleted successfully',
        ]);
    }

    /**
     * Get emails for a customer.
     */
    public function getEmails(Request $request, int $id): JsonResponse
    {
        // Verify customer exists
        $customers = $this->fulfil->getActiveCustomers();
        $customer = collect($customers)->firstWhere('id', $id);

        if (!$customer) {
            abort(404, 'Customer not found');
        }

        $perPage = min($request->integer('per_page', 10), 50);

        $emails = Email::where('fulfil_party_id', $id)
            ->orderBy('email_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'emails' => $emails->map(function ($email) {
                return [
                    'id' => $email->id,
                    'gmail_message_id' => $email->gmail_message_id,
                    'gmail_thread_id' => $email->gmail_thread_id,
                    'direction' => $email->direction,
                    'from_email' => $email->from_email,
                    'from_name' => $email->from_name,
                    'to_emails' => $email->to_emails,
                    'cc_emails' => $email->cc_emails,
                    'subject' => $email->subject,
                    'snippet' => $this->getEmailSnippet($email->body_text, 100),
                    'email_date' => $email->email_date->toIso8601String(),
                    'has_attachments' => $email->has_attachments,
                    'contact_name' => $email->from_name,
                ];
            }),
            'pagination' => [
                'current_page' => $emails->currentPage(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'total' => $emails->total(),
            ],
        ]);
    }

    /**
     * Get a single email with thread context.
     */
    public function getEmail(int $customerId, int $emailId): JsonResponse
    {
        $email = Email::where('fulfil_party_id', $customerId)
            ->where('id', $emailId)
            ->firstOrFail();

        // Get thread emails if this email is part of a thread
        $threadEmails = [];
        if ($email->gmail_thread_id) {
            $threadEmails = Email::where('gmail_thread_id', $email->gmail_thread_id)
                ->where('fulfil_party_id', $customerId)
                ->orderBy('email_date', 'asc')
                ->get()
                ->map(function ($threadEmail) {
                    return [
                        'id' => $threadEmail->id,
                        'direction' => $threadEmail->direction,
                        'from_email' => $threadEmail->from_email,
                        'from_name' => $threadEmail->from_name,
                        'to_emails' => $threadEmail->to_emails,
                        'cc_emails' => $threadEmail->cc_emails,
                        'subject' => $threadEmail->subject,
                        'body_html' => $threadEmail->body_html,
                        'body_text' => $threadEmail->body_text,
                        'email_date' => $threadEmail->email_date->toIso8601String(),
                        'has_attachments' => $threadEmail->has_attachments,
                        'attachment_info' => $threadEmail->attachment_info,
                    ];
                });
        }

        return response()->json([
            'email' => [
                'id' => $email->id,
                'gmail_message_id' => $email->gmail_message_id,
                'gmail_thread_id' => $email->gmail_thread_id,
                'direction' => $email->direction,
                'from_email' => $email->from_email,
                'from_name' => $email->from_name,
                'to_emails' => $email->to_emails,
                'cc_emails' => $email->cc_emails,
                'subject' => $email->subject,
                'body_html' => $email->body_html,
                'body_text' => $email->body_text,
                'email_date' => $email->email_date->toIso8601String(),
                'has_attachments' => $email->has_attachments,
                'attachment_info' => $email->attachment_info,
            ],
            'thread' => $threadEmails,
        ]);
    }

    /**
     * Get a snippet from email body text.
     */
    protected function getEmailSnippet(?string $bodyText, int $length = 100): string
    {
        if (!$bodyText) {
            return '';
        }

        // Remove extra whitespace and newlines
        $text = preg_replace('/\s+/', ' ', trim($bodyText));

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }
}
