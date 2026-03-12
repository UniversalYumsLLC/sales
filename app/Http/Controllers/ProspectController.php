<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\ProspectProduct;
use App\Services\FulfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ProspectController extends Controller
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Display the list of prospects.
     */
    public function index(Request $request): Response
    {
        $prospects = Prospect::orderBy('company_name')
            ->get(['id', 'company_name', 'status', 'created_at']);

        return Inertia::render('Prospects/Index', [
            'prospects' => $prospects,
            'statuses' => Prospect::getStatuses(),
        ]);
    }

    /**
     * Update the status of a prospect.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:target,contacted,engaged,dormant'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid status',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prospect->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated successfully',
            'prospect' => $prospect,
        ]);
    }

    /**
     * Show the form for creating a new prospect.
     */
    public function create(): Response
    {
        $products = $this->fulfil->getProducts();

        // Format products for dropdown
        $productOptions = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
            ];
        }, $products);

        // Sort by name
        usort($productOptions, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return Inertia::render('Prospects/Create', [
            'products' => $productOptions,
        ]);
    }

    /**
     * Store a newly created prospect.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'company_urls' => ['nullable', 'array'],
            'company_urls.*' => ['string', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string', 'min:1', 'max:100'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ], [
            'company_name.required' => 'Company name is required.',
            'company_name.min' => 'Company name must be at least 2 characters.',
            'contacts.*.name.required_with' => 'Contact name is required.',
            'contacts.*.email.email' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($validated) {
                // Collect domains from contact emails
                $contactDomains = [];
                if (!empty($validated['contacts'])) {
                    foreach ($validated['contacts'] as $contact) {
                        $email = $contact['email'] ?? null;
                        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $domain = strtolower(explode('@', $email)[1] ?? '');
                            if (!empty($domain)) {
                                $contactDomains[] = $domain;
                            }
                        }
                    }
                }

                // Merge provided company_urls with auto-extracted domains
                $companyUrls = array_values(array_filter($validated['company_urls'] ?? []));
                foreach (array_unique($contactDomains) as $domain) {
                    if (!in_array($domain, $companyUrls)) {
                        $companyUrls[] = $domain;
                    }
                }

                // Create the prospect
                $prospect = Prospect::create([
                    'company_name' => $validated['company_name'],
                    'created_by' => Auth::id(),
                    'company_urls' => !empty($companyUrls) ? $companyUrls : null,
                ]);

                // Create contacts (all contacts from create form are buyers)
                if (!empty($validated['contacts'])) {
                    foreach ($validated['contacts'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_BUYER,
                                'name' => $contact['name'],
                                'value' => $contact['email'] ?? null,
                            ]);
                        }
                    }
                }

                // Create product associations
                if (!empty($validated['product_ids'])) {
                    foreach ($validated['product_ids'] as $productId) {
                        ProspectProduct::create([
                            'prospect_id' => $prospect->id,
                            'product_id' => $productId,
                        ]);
                    }
                }
            });

            return redirect()
                ->route('prospects.index')
                ->with('success', "Prospect \"{$validated['company_name']}\" created successfully.");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['general' => 'Failed to create prospect: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the prospect detail page.
     */
    public function show(int $id): Response
    {
        $prospect = Prospect::with(['buyers', 'accountsPayable', 'logistics', 'uncategorized', 'brokerContacts', 'products'])->findOrFail($id);

        // Get all products for the dropdown
        $products = $this->fulfil->getProducts();
        $productOptions = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
            ];
        }, $products);
        usort($productOptions, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        // Map product IDs to product details
        $productsById = collect($productOptions)->keyBy('id');
        $prospectProducts = $prospect->products->map(function ($pp) use ($productsById) {
            $product = $productsById->get($pp->product_id);
            return $product ? [
                'id' => $pp->product_id,
                'name' => $product['name'],
                'sku' => $product['sku'],
            ] : null;
        })->filter()->values()->toArray();

        return Inertia::render('Prospects/Show', [
            'prospect' => [
                'id' => $prospect->id,
                'company_name' => $prospect->company_name,
                'status' => $prospect->status,
                'notes' => $prospect->notes,
                'discount_percent' => $prospect->discount_percent,
                'payment_terms' => $prospect->payment_terms,
                'shipping_terms' => $prospect->shipping_terms,
                'shelf_life_requirement' => $prospect->shelf_life_requirement,
                'vendor_guide' => $prospect->vendor_guide,
                'company_urls' => $prospect->company_urls ?? [],
                'broker' => $prospect->broker ?? false,
                'broker_commission' => $prospect->broker_commission,
                'broker_company_name' => $prospect->broker_company_name,
                'broker_contacts' => $prospect->brokerContacts->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
                    'last_received_at' => $c->last_received_at?->toIso8601String(),
                ])->toArray(),
                'buyers' => $prospect->buyers->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
                    'last_received_at' => $c->last_received_at?->toIso8601String(),
                ])->toArray(),
                'accounts_payable' => $prospect->accountsPayable->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                ])->toArray(),
                'logistics' => $prospect->logistics->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                ])->toArray(),
                'uncategorized' => $prospect->uncategorized->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
                    'last_received_at' => $c->last_received_at?->toIso8601String(),
                ])->toArray(),
                'products' => $prospectProducts,
                'product_ids' => $prospect->products->pluck('product_id')->toArray(),
            ],
            'statuses' => Prospect::getStatuses(),
            'allProducts' => $productOptions,
            // Form options for dropdowns (same as Active Customers)
            'priceLists' => $this->fulfil->getAllPriceLists(),
            'paymentTerms' => $this->fulfil->getAllPaymentTerms(),
            'shippingTerms' => $this->fulfil->getShippingTermsCategories(),
        ]);
    }

    /**
     * Update an existing prospect.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'status' => ['sometimes', 'in:target,contacted,engaged,dormant'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'shipping_terms' => ['nullable', 'string', 'max:255'],
            'shelf_life_requirement' => ['nullable', 'integer', 'min:1'],
            'vendor_guide' => ['nullable', 'string', 'max:500', 'url'],
            'company_urls' => ['sometimes', 'array'],
            'company_urls.*' => ['string', 'max:255'],
            'broker' => ['sometimes', 'boolean'],
            'broker_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'broker_company_name' => ['nullable', 'string', 'max:255'],
            'broker_contacts' => ['sometimes', 'array'],
            'broker_contacts.*.name' => ['required_with:broker_contacts', 'string', 'min:1', 'max:100'],
            'broker_contacts.*.value' => ['nullable', 'email', 'max:255'],
            'buyers' => ['sometimes', 'array'],
            'buyers.*.name' => ['required_with:buyers', 'string', 'min:1', 'max:100'],
            'buyers.*.value' => ['nullable', 'email', 'max:255'],
            'accounts_payable' => ['sometimes', 'array'],
            'accounts_payable.*.name' => ['required_with:accounts_payable', 'string', 'min:1', 'max:100'],
            'accounts_payable.*.value' => ['nullable', 'string', 'max:500'],
            'logistics' => ['sometimes', 'array'],
            'logistics.*.name' => ['required_with:logistics', 'string', 'min:1', 'max:100'],
            'logistics.*.value' => ['nullable', 'email', 'max:255'],
            'uncategorized' => ['sometimes', 'array'],
            'uncategorized.*.name' => ['required_with:uncategorized', 'string', 'min:1', 'max:100'],
            'uncategorized.*.value' => ['nullable', 'email', 'max:255'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer'],
        ], [
            'company_name.min' => 'Company name must be at least 2 characters.',
            'buyers.*.name.required_with' => 'Buyer name is required.',
            'buyers.*.value.email' => 'Please enter a valid email address.',
            'accounts_payable.*.name.required_with' => 'AP contact name is required.',
            'logistics.*.name.required_with' => 'Logistics contact name is required.',
            'logistics.*.value.email' => 'Please enter a valid email address.',
            'uncategorized.*.name.required_with' => 'Contact name is required.',
            'uncategorized.*.value.email' => 'Please enter a valid email address.',
            'broker_contacts.*.name.required_with' => 'Broker contact name is required.',
            'broker_contacts.*.value.email' => 'Please enter a valid email address for broker contact.',
            'vendor_guide.url' => 'Please enter a valid URL.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($prospect, $validated) {
                // Update basic fields
                $updateData = [];
                if (isset($validated['company_name'])) {
                    $updateData['company_name'] = $validated['company_name'];
                }
                if (isset($validated['status'])) {
                    $updateData['status'] = $validated['status'];
                }
                if (array_key_exists('notes', $validated)) {
                    $updateData['notes'] = $validated['notes'];
                }
                if (array_key_exists('discount_percent', $validated)) {
                    $updateData['discount_percent'] = $validated['discount_percent'];
                }
                if (array_key_exists('payment_terms', $validated)) {
                    $updateData['payment_terms'] = $validated['payment_terms'];
                }
                if (array_key_exists('shipping_terms', $validated)) {
                    $updateData['shipping_terms'] = $validated['shipping_terms'];
                }
                if (array_key_exists('shelf_life_requirement', $validated)) {
                    $updateData['shelf_life_requirement'] = $validated['shelf_life_requirement'];
                }
                if (array_key_exists('vendor_guide', $validated)) {
                    $updateData['vendor_guide'] = $validated['vendor_guide'];
                }
                if (array_key_exists('company_urls', $validated)) {
                    $updateData['company_urls'] = array_values(array_filter($validated['company_urls']));
                }
                if (array_key_exists('broker', $validated)) {
                    $updateData['broker'] = $validated['broker'];
                }
                if (array_key_exists('broker_commission', $validated)) {
                    $updateData['broker_commission'] = $validated['broker_commission'];
                }
                if (array_key_exists('broker_company_name', $validated)) {
                    $updateData['broker_company_name'] = $validated['broker_company_name'];
                }
                if (!empty($updateData)) {
                    $prospect->update($updateData);
                }

                // Auto-extract domains from contact emails and add to company_urls
                $this->autoAddDomainsFromContacts($prospect, $validated);

                // Update buyers if provided
                if (isset($validated['buyers'])) {
                    $prospect->buyers()->delete();
                    foreach ($validated['buyers'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_BUYER,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update accounts payable if provided
                if (isset($validated['accounts_payable'])) {
                    $prospect->accountsPayable()->delete();
                    foreach ($validated['accounts_payable'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_ACCOUNTS_PAYABLE,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update logistics if provided
                if (isset($validated['logistics'])) {
                    $prospect->logistics()->delete();
                    foreach ($validated['logistics'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_LOGISTICS,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update uncategorized contacts if provided
                if (isset($validated['uncategorized'])) {
                    $prospect->uncategorized()->delete();
                    foreach ($validated['uncategorized'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_UNCATEGORIZED,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update broker contacts if provided
                if (isset($validated['broker_contacts'])) {
                    $prospect->brokerContacts()->delete();
                    foreach ($validated['broker_contacts'] as $contact) {
                        if (!empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_BROKER,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update products if provided
                if (isset($validated['product_ids'])) {
                    // Delete existing and recreate
                    $prospect->products()->delete();
                    foreach ($validated['product_ids'] as $productId) {
                        ProspectProduct::create([
                            'prospect_id' => $prospect->id,
                            'product_id' => $productId,
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Prospect updated successfully',
                'prospect' => $prospect->fresh(['buyers', 'accountsPayable', 'logistics', 'products']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update prospect',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-extract email domains from contacts and add to company_urls.
     */
    protected function autoAddDomainsFromContacts(Prospect $prospect, array $validated): void
    {
        $contactTypes = ['buyers', 'accounts_payable', 'logistics', 'uncategorized'];
        $newDomains = [];

        foreach ($contactTypes as $type) {
            if (!isset($validated[$type])) {
                continue;
            }

            foreach ($validated[$type] as $contact) {
                $email = $contact['value'] ?? null;
                if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $domain = strtolower(explode('@', $email)[1] ?? '');
                if (!empty($domain)) {
                    $newDomains[] = $domain;
                }
            }
        }

        // Add any new domains that aren't already in company_urls
        if (!empty($newDomains)) {
            $existingDomains = $prospect->getEmailDomains();
            foreach (array_unique($newDomains) as $domain) {
                if (!in_array($domain, $existingDomains)) {
                    $prospect->addCompanyUrl($domain);
                }
            }
        }
    }

    /**
     * Categorize an uncategorized contact.
     */
    public function categorizeContact(Request $request, int $prospectId, int $contactId): JsonResponse
    {
        $prospect = Prospect::findOrFail($prospectId);
        $contact = ProspectContact::where('prospect_id', $prospectId)
            ->where('id', $contactId)
            ->firstOrFail();

        // Only allow categorization of uncategorized contacts
        if ($contact->type !== ProspectContact::TYPE_UNCATEGORIZED) {
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
                'value' => $contact->value,
                'type' => $contact->type,
            ],
        ]);
    }
}
