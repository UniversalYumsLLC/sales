<?php

namespace App\Http\Controllers;

use App\Exceptions\FulfilUnavailableException;
use App\Jobs\SyncGmailForDomains;
use App\Models\Email;
use App\Models\FulfilBrokerContact;
use App\Models\FulfilUncategorizedContact;
use App\Models\LocalCustomerMetadata;
use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\ProspectProduct;
use App\Services\FulfilService;
use App\Support\CompanyFields;
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
        usort($productOptions, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

        return Inertia::render('Prospects/Create', [
            'products' => $productOptions,
            'priceLists' => $this->fulfil->getAllPriceLists(),
            'paymentTerms' => $this->fulfil->getAllPaymentTerms(),
            'shippingTerms' => $this->fulfil->getShippingTermsCategories(),
        ]);
    }

    /**
     * Store a newly created prospect.
     *
     * Only company_name is required. All other fields (commercial terms,
     * contacts, broker, invoicing fields, etc.) are optional.
     */
    public function store(Request $request): RedirectResponse
    {
        $sanitized = CompanyFields::sanitizeContacts($request->all());

        $rules = array_merge(
            ['company_name' => ['required', 'string', 'min:2', 'max:255']],
            CompanyFields::prospectCommercialRules(isSometimes: false),
            CompanyFields::sharedRules(required: false, sometimes: false),
            CompanyFields::prospectOnlyRules(isSometimes: false),
        );

        $validator = Validator::make($sanitized, $rules, CompanyFields::messages());

        $validator->after(function ($validator) use ($sanitized) {
            CompanyFields::addAfterValidation($validator, $sanitized);
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            $prospect = null;
            $companyUrls = [];

            DB::transaction(function () use ($validated, &$prospect, &$companyUrls) {
                // Collect domains from buyer/contact emails
                $contactDomains = [];
                foreach (['buyers', 'other'] as $contactType) {
                    foreach ($validated[$contactType] ?? [] as $contact) {
                        $email = $contact['email'] ?? null;
                        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $domain = strtolower(explode('@', $email)[1] ?? '');
                            if (! empty($domain)) {
                                $contactDomains[] = $domain;
                            }
                        }
                    }
                }
                foreach ($validated['accounts_payable'] ?? [] as $ap) {
                    $value = $ap['value'] ?? '';
                    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $domain = strtolower(explode('@', $value)[1] ?? '');
                        if (! empty($domain)) {
                            $contactDomains[] = $domain;
                        }
                    }
                }

                // Merge provided company_urls with auto-extracted domains
                $companyUrls = array_values(array_filter($validated['company_urls'] ?? []));
                foreach (array_unique($contactDomains) as $domain) {
                    if (! in_array($domain, $companyUrls)) {
                        $companyUrls[] = $domain;
                    }
                }

                // Build prospect data from validated fields
                $prospectData = [
                    'company_name' => $validated['company_name'],
                    'created_by' => Auth::id(),
                    'company_urls' => ! empty($companyUrls) ? $companyUrls : null,
                ];

                // Add optional scalar fields if provided
                $optionalFields = [
                    'discount_percent', 'payment_terms', 'shipping_terms',
                    'shelf_life_requirement', 'vendor_guide', 'broker',
                    'broker_commission', 'broker_company_name', 'customer_type',
                    'ar_edi', 'ar_consolidated_invoicing',
                    'ar_requires_customer_skus', 'ar_invoice_discount',
                ];
                foreach ($optionalFields as $field) {
                    if (array_key_exists($field, $validated)) {
                        $prospectData[$field] = $validated[$field];
                    }
                }

                $prospect = Prospect::create($prospectData);

                // Create buyer contacts
                foreach ($validated['buyers'] ?? [] as $contact) {
                    if (! empty($contact['name'])) {
                        ProspectContact::create([
                            'prospect_id' => $prospect->id,
                            'type' => ProspectContact::TYPE_BUYER,
                            'name' => $contact['name'],
                            'value' => $contact['email'] ?? null,
                        ]);
                    }
                }

                // Create AP contacts
                foreach ($validated['accounts_payable'] ?? [] as $contact) {
                    if (! empty($contact['name'])) {
                        ProspectContact::create([
                            'prospect_id' => $prospect->id,
                            'type' => ProspectContact::TYPE_ACCOUNTS_PAYABLE,
                            'name' => $contact['name'],
                            'value' => $contact['value'] ?? null,
                        ]);
                    }
                }

                // Create other contacts
                foreach ($validated['other'] ?? [] as $contact) {
                    if (! empty($contact['name'])) {
                        ProspectContact::create([
                            'prospect_id' => $prospect->id,
                            'type' => ProspectContact::TYPE_OTHER,
                            'name' => $contact['name'],
                            'value' => $contact['email'] ?? null,
                            'function' => $contact['function'] ?? null,
                        ]);
                    }
                }

                // Create broker contacts
                foreach ($validated['broker_contacts'] ?? [] as $contact) {
                    if (! empty($contact['name'])) {
                        ProspectContact::create([
                            'prospect_id' => $prospect->id,
                            'type' => ProspectContact::TYPE_BROKER,
                            'name' => $contact['name'],
                            'value' => $contact['email'] ?? null,
                        ]);
                    }
                }

                // Create product associations
                foreach ($validated['product_ids'] ?? [] as $productId) {
                    ProspectProduct::create([
                        'prospect_id' => $prospect->id,
                        'product_id' => $productId,
                    ]);
                }
            });

            // Dispatch Gmail sync job for the new prospect's domains (runs in background)
            if ($prospect && ! empty($companyUrls)) {
                SyncGmailForDomains::dispatch($companyUrls, 'prospect', $prospect->id);
            }

            return redirect()
                ->route('prospects.index')
                ->with('success', "Prospect \"{$validated['company_name']}\" created successfully.");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['general' => 'Failed to create prospect: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the prospect detail page.
     */
    public function show(int $id): Response
    {
        $prospect = Prospect::with(['buyers', 'accountsPayable', 'other', 'uncategorized', 'brokerContacts', 'products'])->findOrFail($id);

        // Get all products for the dropdown
        $products = $this->fulfil->getProducts();
        $productOptions = array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
            ];
        }, $products);
        usort($productOptions, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));

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
                'customer_type' => $prospect->customer_type,
                'ar_edi' => $prospect->ar_edi ?? false,
                'ar_consolidated_invoicing' => $prospect->ar_consolidated_invoicing ?? false,
                'ar_requires_customer_skus' => $prospect->ar_requires_customer_skus ?? false,
                'ar_invoice_discount' => $prospect->ar_invoice_discount,
                'broker_contacts' => $prospect->brokerContacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
                    'last_received_at' => $c->last_received_at?->toIso8601String(),
                ])->toArray(),
                'buyers' => $prospect->buyers->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'last_emailed_at' => $c->last_emailed_at?->toIso8601String(),
                    'last_received_at' => $c->last_received_at?->toIso8601String(),
                ])->toArray(),
                'accounts_payable' => $prospect->accountsPayable->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                ])->toArray(),
                'other' => $prospect->other->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'value' => $c->value,
                    'function' => $c->function,
                ])->toArray(),
                'uncategorized' => $prospect->uncategorized->map(fn ($c) => [
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
     *
     * Uses CompanyFields as single source of truth for validation rules.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        $sanitized = CompanyFields::sanitizeContacts($request->all());

        $rules = array_merge(
            ['company_name' => ['sometimes', 'string', 'min:2', 'max:255']],
            CompanyFields::prospectCommercialRules(isSometimes: true),
            CompanyFields::sharedRules(required: false, sometimes: true),
            CompanyFields::prospectOnlyRules(isSometimes: true),
        );

        // Prospect Show page sends buyer contacts with 'value' not 'email'.
        // Override the buyer rules to accept 'value' for backward compatibility.
        $rules['buyers.*.value'] = ['nullable', 'email', 'max:255'];
        unset($rules['buyers.*.email']);

        // Broker contacts from Show page also use 'value'
        $rules['broker_contacts.*.value'] = ['nullable', 'email', 'max:255'];
        unset($rules['broker_contacts.*.email']);

        // Other contacts from Show page use 'value'
        $rules['other.*.value'] = ['nullable', 'email', 'max:255'];
        unset($rules['other.*.email']);

        $validator = Validator::make($sanitized, $rules, CompanyFields::messages());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            DB::transaction(function () use ($prospect, $validated) {
                // Update scalar fields present in the request
                $updateData = [];
                $scalarFields = [
                    'company_name', 'status', 'notes',
                    'discount_percent', 'payment_terms', 'shipping_terms',
                    'shelf_life_requirement', 'vendor_guide',
                    'broker', 'broker_commission', 'broker_company_name',
                    'customer_type',
                    'ar_edi', 'ar_consolidated_invoicing',
                    'ar_requires_customer_skus', 'ar_invoice_discount',
                ];
                foreach ($scalarFields as $field) {
                    if (array_key_exists($field, $validated)) {
                        $updateData[$field] = $validated[$field];
                    }
                }
                if (array_key_exists('company_urls', $validated)) {
                    $updateData['company_urls'] = array_values(array_filter($validated['company_urls']));
                }
                if (! empty($updateData)) {
                    $prospect->update($updateData);
                }

                // Auto-extract domains from contact emails and add to company_urls
                $this->autoAddDomainsFromContacts($prospect, $validated);

                // Update buyers if provided
                if (isset($validated['buyers'])) {
                    $prospect->buyers()->delete();
                    foreach ($validated['buyers'] as $contact) {
                        if (! empty($contact['name'])) {
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
                        if (! empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_ACCOUNTS_PAYABLE,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                            ]);
                        }
                    }
                }

                // Update other contacts if provided
                if (isset($validated['other'])) {
                    $prospect->other()->delete();
                    foreach ($validated['other'] as $contact) {
                        if (! empty($contact['name'])) {
                            ProspectContact::create([
                                'prospect_id' => $prospect->id,
                                'type' => ProspectContact::TYPE_OTHER,
                                'name' => $contact['name'],
                                'value' => $contact['value'] ?? null,
                                'function' => $contact['function'] ?? null,
                            ]);
                        }
                    }
                }

                // Update uncategorized contacts if provided
                if (isset($validated['uncategorized'])) {
                    $prospect->uncategorized()->delete();
                    foreach ($validated['uncategorized'] as $contact) {
                        if (! empty($contact['name'])) {
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
                        if (! empty($contact['name'])) {
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
                'prospect' => $prospect->fresh(['buyers', 'accountsPayable', 'other', 'products']),
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
        $contactTypes = ['buyers', 'accounts_payable', 'other', 'uncategorized'];
        $newDomains = [];

        foreach ($contactTypes as $type) {
            if (! isset($validated[$type])) {
                continue;
            }

            foreach ($validated[$type] as $contact) {
                $email = $contact['value'] ?? null;
                if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $domain = strtolower(explode('@', $email)[1] ?? '');
                if (! empty($domain)) {
                    $newDomains[] = $domain;
                }
            }
        }

        // Add any new domains that aren't already in company_urls
        if (! empty($newDomains)) {
            $existingDomains = $prospect->getEmailDomains();
            foreach (array_unique($newDomains) as $domain) {
                if (! in_array($domain, $existingDomains)) {
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
            'type' => ['required', 'in:buyer,accounts_payable,other'],
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

    /**
     * Get emails for a prospect.
     */
    public function getEmails(Request $request, int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        $perPage = min($request->integer('per_page', 10), 50);

        $emails = Email::where('prospect_id', $id)
            ->with('contact:id,name,value')
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
                    'contact_name' => $email->contact?->name,
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
    public function getEmail(int $prospectId, int $emailId): JsonResponse
    {
        $prospect = Prospect::findOrFail($prospectId);

        $email = Email::where('prospect_id', $prospectId)
            ->where('id', $emailId)
            ->with('contact:id,name,value')
            ->firstOrFail();

        // Get thread emails if this email is part of a thread
        $threadEmails = [];
        if ($email->gmail_thread_id) {
            $threadEmails = Email::where('gmail_thread_id', $email->gmail_thread_id)
                ->where('prospect_id', $prospectId)
                ->with('contact:id,name,value')
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
                        'contact_name' => $threadEmail->contact?->name,
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
                'contact_name' => $email->contact?->name,
            ],
            'thread' => $threadEmails,
        ]);
    }

    /**
     * Get a snippet from email body text.
     */
    protected function getEmailSnippet(?string $bodyText, int $length = 100): string
    {
        if (! $bodyText) {
            return '';
        }

        // Remove extra whitespace and newlines
        $text = preg_replace('/\s+/', ' ', trim($bodyText));

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length).'...';
    }

    /**
     * Promote a prospect to an active customer.
     */
    public function promote(int $id): JsonResponse|RedirectResponse
    {
        $prospect = Prospect::with(['buyers', 'accountsPayable', 'other', 'brokerContacts', 'uncategorized'])
            ->findOrFail($id);

        // Get form options for validation
        $priceLists = $this->fulfil->getAllPriceLists();
        $paymentTerms = $this->fulfil->getAllPaymentTerms();
        $shippingTerms = $this->fulfil->getShippingTermsCategories();

        // Build validation errors
        $errors = [];

        // Validate company_name
        if (empty($prospect->company_name) || strlen($prospect->company_name) < 2) {
            $errors['company_name'] = 'Company name must be at least 2 characters';
        }

        // Validate discount_percent (must map to a valid price list)
        $priceListId = null;
        if ($prospect->discount_percent === null) {
            $errors['discount_percent'] = 'Discount level is required';
        } else {
            $matchedPriceList = collect($priceLists)->firstWhere('discount_percent', $prospect->discount_percent);
            if (! $matchedPriceList) {
                $errors['discount_percent'] = 'Invalid discount level';
            } else {
                $priceListId = $matchedPriceList['id'];
            }
        }

        // Validate payment_terms (must map to a valid payment term)
        $paymentTermId = null;
        if (empty($prospect->payment_terms)) {
            $errors['payment_terms'] = 'Payment terms is required';
        } else {
            $matchedPaymentTerm = collect($paymentTerms)->firstWhere('name', $prospect->payment_terms);
            if (! $matchedPaymentTerm) {
                $errors['payment_terms'] = 'Invalid payment terms';
            } else {
                $paymentTermId = $matchedPaymentTerm['id'];
            }
        }

        // Validate shipping_terms (must map to a valid shipping term)
        $shippingTermId = null;
        if (empty($prospect->shipping_terms)) {
            $errors['shipping_terms'] = 'Shipping terms is required';
        } else {
            $matchedShippingTerm = collect($shippingTerms)->firstWhere('name', $prospect->shipping_terms);
            if (! $matchedShippingTerm) {
                $errors['shipping_terms'] = 'Invalid shipping terms';
            } else {
                $shippingTermId = $matchedShippingTerm['id'];
            }
        }

        // Validate shelf_life_requirement
        if ($prospect->shelf_life_requirement === null) {
            $errors['shelf_life_requirement'] = 'Shelf life requirement is required';
        } elseif ($prospect->shelf_life_requirement < 30 || $prospect->shelf_life_requirement > 365) {
            $errors['shelf_life_requirement'] = 'Shelf life requirement must be between 30 and 365 days';
        }

        // Validate vendor_guide (if set, must be valid URL)
        if (! empty($prospect->vendor_guide) && ! filter_var($prospect->vendor_guide, FILTER_VALIDATE_URL)) {
            $errors['vendor_guide'] = 'Vendor guide must be a valid URL';
        }

        // Validate buyers (at least 1 required)
        $buyers = $prospect->buyers;
        if ($buyers->isEmpty()) {
            $errors['buyers'] = 'At least one buyer contact is required';
        } else {
            foreach ($buyers as $index => $buyer) {
                if (empty($buyer->name) || strlen($buyer->name) < 2) {
                    $errors["buyers.{$index}.name"] = 'Buyer name must be at least 2 characters';
                }
                if (empty($buyer->value) || ! filter_var($buyer->value, FILTER_VALIDATE_EMAIL)) {
                    $errors["buyers.{$index}.value"] = 'Buyer email is required and must be valid';
                }
            }
        }

        // Validate AP contacts (optional, but if present must be valid)
        foreach ($prospect->accountsPayable as $index => $ap) {
            if (empty($ap->name) || strlen($ap->name) < 2) {
                $errors["accounts_payable.{$index}.name"] = 'AP contact name must be at least 2 characters';
            }
        }

        // Validate other contacts (optional, but if present must be valid)
        foreach ($prospect->other as $index => $other) {
            if (empty($other->name) || strlen($other->name) < 2) {
                $errors["other.{$index}.name"] = 'Other contact name must be at least 2 characters';
            }
        }

        // Validate broker field is set (required)
        if ($prospect->broker === null) {
            $errors['broker'] = 'Broker selection is required';
        }

        // If broker is TRUE, validate broker fields
        if ($prospect->broker === true) {
            // Broker company name required
            if (empty($prospect->broker_company_name)) {
                $errors['broker_company_name'] = 'Broker company name is required when using a broker';
            }

            // Broker commission required
            if ($prospect->broker_commission === null) {
                $errors['broker_commission'] = 'Broker commission is required when using a broker';
            }

            // At least one broker contact required
            if ($prospect->brokerContacts->isEmpty()) {
                $errors['broker_contacts'] = 'At least one broker contact is required when using a broker';
            } else {
                foreach ($prospect->brokerContacts as $index => $brokerContact) {
                    if (empty($brokerContact->name) || strlen($brokerContact->name) < 2) {
                        $errors["broker_contacts.{$index}.name"] = 'Broker contact name must be at least 2 characters';
                    }
                    if (empty($brokerContact->value) || ! filter_var($brokerContact->value, FILTER_VALIDATE_EMAIL)) {
                        $errors["broker_contacts.{$index}.value"] = 'Broker contact email is required and must be valid';
                    }
                }
            }
        }

        // If there are validation errors, return them for Inertia
        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        // Transform data for customer creation in Fulfil.
        // Local-only fields (broker, vendor_guide, AR settings except invoice_discount)
        // are saved to local_customer_metadata below, not sent to Fulfil.
        $customerData = [
            'name' => $prospect->company_name,
            'sale_price_list' => $priceListId,
            'customer_payment_term' => $paymentTermId,
            'shipping_terms_category_id' => $shippingTermId,
            'shelf_life_requirement' => $prospect->shelf_life_requirement,
            'buyers' => $buyers->map(fn ($b) => ['name' => $b->name, 'email' => $b->value])->toArray(),
            'accounts_payable' => $prospect->accountsPayable->map(fn ($ap) => ['name' => $ap->name, 'value' => $ap->value])->toArray(),
            'other' => $prospect->other->map(fn ($o) => ['name' => $o->name, 'email' => $o->value, 'function' => $o->function])->toArray(),
        ];

        // Add broker contacts if broker is enabled
        if ($prospect->broker && $prospect->brokerContacts->isNotEmpty()) {
            $customerData['broker_contacts'] = $prospect->brokerContacts->map(fn ($bc) => [
                'name' => $bc->name,
                'email' => $bc->value,
            ])->toArray();
        }

        try {
            DB::beginTransaction();

            // Create customer in Fulfil
            $result = $this->fulfil->createCustomer($customerData);
            $partyId = $result['id'];

            // Create local metadata (includes all locally-stored fields)
            $metadata = LocalCustomerMetadata::create([
                'fulfil_party_id' => $partyId,
                'company_urls' => $prospect->company_urls ?? [],
                'customer_type' => $prospect->customer_type,
                'broker' => $prospect->broker ?? false,
                'broker_commission' => $prospect->broker_commission,
                'broker_company_name' => $prospect->broker_company_name,
                'ar_edi' => $prospect->ar_edi ?? false,
                'ar_consolidated_invoicing' => $prospect->ar_consolidated_invoicing ?? false,
                'ar_requires_customer_skus' => $prospect->ar_requires_customer_skus ?? false,
                'vendor_guide' => $prospect->vendor_guide,
            ]);

            // Create broker contact records for email tracking
            if ($prospect->broker) {
                foreach ($prospect->brokerContacts as $brokerContact) {
                    if (! empty($brokerContact->value)) {
                        FulfilBrokerContact::create([
                            'fulfil_party_id' => $partyId,
                            'name' => $brokerContact->name,
                            'email' => strtolower($brokerContact->value),
                            'last_emailed_at' => $brokerContact->last_emailed_at,
                            'last_received_at' => $brokerContact->last_received_at,
                        ]);
                    }
                }
            }

            // Migrate uncategorized contacts
            foreach ($prospect->uncategorized as $uncategorized) {
                if (! empty($uncategorized->value)) {
                    FulfilUncategorizedContact::create([
                        'fulfil_party_id' => $partyId,
                        'name' => $uncategorized->name,
                        'email' => strtolower($uncategorized->value),
                        'last_emailed_at' => $uncategorized->last_emailed_at,
                        'last_received_at' => $uncategorized->last_received_at,
                    ]);
                }
            }

            // Sync invoice_discount to Fulfil metafields (only AR field still in Fulfil)
            if ($prospect->ar_invoice_discount !== null) {
                try {
                    $this->fulfil->updateCustomerArSettings($partyId, [
                        'invoice_discount' => (float) $prospect->ar_invoice_discount,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to save invoice discount during promotion', [
                        'party_id' => $partyId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete the prospect (cascades to contacts and products)
            $prospect->delete();

            DB::commit();

            // Dispatch Gmail sync for the new customer's domains (runs in background, after commit)
            $companyUrls = $prospect->company_urls ?? [];
            if (! empty($companyUrls)) {
                SyncGmailForDomains::dispatch($companyUrls, 'customer', $partyId);
            }

            // Redirect to the new customer page with success message
            return redirect()
                ->route('customers.show', $partyId)
                ->with('success', "Successfully promoted \"{$customerData['name']}\" to active customer!");

        } catch (FulfilUnavailableException $e) {
            DB::rollBack();

            return back()->withErrors([
                'general' => $e->getUserMessage(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'general' => 'Failed to promote prospect to customer: '.$e->getMessage(),
            ]);
        }
    }
}
