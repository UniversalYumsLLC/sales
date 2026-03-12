<?php

namespace App\Http\Controllers;

use App\Models\FulfilContactMetadata;
use App\Models\FulfilCustomerMetadata;
use App\Services\FulfilService;
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

        // Merge in local metadata (company_urls for Gmail matching)
        $localMetadata = FulfilCustomerMetadata::find($id);
        $customer['company_urls'] = $localMetadata?->company_urls ?? [];

        // Merge email tracking dates into buyer contacts
        $buyerContacts = $this->mergeContactEmailMetadata($id, $customer['buyers'] ?? []);

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
}
