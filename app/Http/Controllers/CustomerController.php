<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGmailForDomains;
use App\Services\FulfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    protected FulfilService $fulfil;

    public function __construct(FulfilService $fulfil)
    {
        $this->fulfil = $fulfil;
    }

    /**
     * Show the create customer form.
     */
    public function create(): Response
    {
        return Inertia::render('ActiveCustomers/Create', [
            'priceLists' => $this->fulfil->getAllPriceLists(),
            'paymentTerms' => $this->fulfil->getAllPaymentTerms(),
            'shippingTerms' => $this->fulfil->getShippingTermsCategories(),
        ]);
    }

    /**
     * Get form options for customer create/edit forms (JSON API).
     * Returns price lists, payment terms, and shipping terms.
     */
    public function formOptions(Request $request): JsonResponse
    {
        $bustCache = $request->boolean('refresh');

        return response()->json([
            'price_lists' => $this->fulfil->getAllPriceLists($bustCache),
            'payment_terms' => $this->fulfil->getAllPaymentTerms($bustCache),
            'shipping_terms' => $this->fulfil->getShippingTermsCategories($bustCache),
        ]);
    }

    /**
     * Create a new customer.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = $this->validateCustomerData($request->all(), isCreate: true);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $this->transformRequestData($validator->validated());

        try {
            $customer = $this->fulfil->createCustomer($data);

            // Extract domains from buyer emails for Gmail sync
            $domains = $this->extractDomainsFromContacts($data);

            // Dispatch Gmail sync job for the new customer's domains (runs in background)
            if (! empty($domains) && isset($customer['id'])) {
                SyncGmailForDomains::dispatch($domains, 'customer', $customer['id']);
            }

            // Save AR settings to Fulfil metafields if any were provided
            if (isset($customer['id'])) {
                $arSettings = [];
                if (isset($data['ar_edi'])) {
                    $arSettings['edi'] = $data['ar_edi'];
                }
                if (isset($data['ar_consolidated_invoicing']) && $data['ar_consolidated_invoicing']) {
                    $arSettings['consolidated_invoicing'] = $data['ar_consolidated_invoicing'];
                }
                if (isset($data['ar_requires_customer_skus'])) {
                    $arSettings['requires_customer_skus'] = $data['ar_requires_customer_skus'];
                }
                if (isset($data['ar_invoice_discount']) && $data['ar_invoice_discount'] !== '' && $data['ar_invoice_discount'] !== null) {
                    $arSettings['invoice_discount'] = (float) $data['ar_invoice_discount'];
                }

                if (! empty($arSettings)) {
                    try {
                        $this->fulfil->updateCustomerArSettings($customer['id'], $arSettings);
                    } catch (\Exception $e) {
                        // Log but don't fail the customer creation
                        \Log::warning('Failed to save AR settings for new customer', [
                            'customer_id' => $customer['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Redirect to index since new customers won't have orders yet
            // and won't appear in the active customers detail view
            return redirect()
                ->route('customers.index')
                ->with('success', "Customer \"{$customer['name']}\" created successfully.");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['general' => 'Failed to create customer: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Extract unique domains from contact emails (buyers, other, accounts_payable).
     */
    protected function extractDomainsFromContacts(array $data): array
    {
        $domains = [];

        // Extract from buyers
        foreach ($data['buyers'] ?? [] as $buyer) {
            if (! empty($buyer['email']) && filter_var($buyer['email'], FILTER_VALIDATE_EMAIL)) {
                $domain = strtolower(explode('@', $buyer['email'])[1] ?? '');
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        // Extract from other contacts
        foreach ($data['other'] ?? [] as $contact) {
            if (! empty($contact['email']) && filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                $domain = strtolower(explode('@', $contact['email'])[1] ?? '');
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        // Extract from accounts_payable (if it's an email)
        foreach ($data['accounts_payable'] ?? [] as $contact) {
            $value = $contact['value'] ?? '';
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $domain = strtolower(explode('@', $value)[1] ?? '');
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Update an existing customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = $this->validateCustomerData($request->all(), isCreate: false);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->transformRequestData($validator->validated());

        try {
            $customer = $this->fulfil->updateCustomer($id, $data);

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update customer', [
                'customer_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update customer: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate customer data for create or update.
     */
    protected function validateCustomerData(array $data, bool $isCreate): \Illuminate\Validation\Validator
    {
        $rules = [
            // Core fields
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'min:2', 'max:255'],

            // Commercial terms
            'sale_price_list' => [$isCreate ? 'required' : 'sometimes', 'integer'],
            'customer_payment_term' => [$isCreate ? 'required' : 'sometimes', 'integer'],
            'shipping_terms_category_id' => [$isCreate ? 'required' : 'sometimes', 'integer'],

            // Custom data
            'shelf_life_requirement' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:30', 'max:365'],
            'vendor_guide' => ['nullable', 'url', 'max:500'],

            // Broker information
            'broker' => [$isCreate ? 'required' : 'sometimes', 'boolean'],
            'broker_company_name' => ['nullable', 'string', 'max:255'],
            'broker_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'broker_contacts' => ['nullable', 'array'],
            'broker_contacts.*.name' => ['required_with:broker_contacts', 'string', 'min:2', 'max:100'],
            'broker_contacts.*.email' => ['required_with:broker_contacts', 'email', 'max:255'],

            // Buyers (required for create, at least 1)
            'buyers' => [$isCreate ? 'required' : 'sometimes', 'array', $isCreate ? 'min:1' : 'min:0'],
            'buyers.*.name' => ['required_with:buyers', 'string', 'min:2', 'max:100'],
            'buyers.*.email' => ['required_with:buyers', 'email', 'max:255'],

            // Accounts Payable (optional, can be email or URL)
            'accounts_payable' => ['nullable', 'array'],
            'accounts_payable.*.name' => ['required_with:accounts_payable', 'string', 'min:2', 'max:100'],
            'accounts_payable.*.value' => ['required_with:accounts_payable', 'string', 'max:500'],

            // Other contacts (optional)
            'other' => ['nullable', 'array'],
            'other.*.name' => ['required_with:other', 'string', 'min:2', 'max:100'],
            'other.*.email' => ['required_with:other', 'email', 'max:255'],
            'other.*.function' => ['nullable', 'string', 'max:100'],

            // AR Settings (optional)
            'ar_edi' => ['nullable', 'boolean'],
            'ar_consolidated_invoicing' => ['nullable', 'string', 'in:single_invoice,consolidated_invoice'],
            'ar_requires_customer_skus' => ['nullable', 'boolean'],
            'ar_invoice_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];

        $messages = [
            'name.required' => 'Company name is required.',
            'name.min' => 'Company name must be at least 2 characters.',
            'sale_price_list.required' => 'Please select a discount level.',
            'customer_payment_term.required' => 'Please select payment terms.',
            'shipping_terms_category_id.required' => 'Please select shipping terms.',
            'shelf_life_requirement.required' => 'Shelf life requirement is required.',
            'shelf_life_requirement.min' => 'Shelf life requirement must be at least 30 days.',
            'shelf_life_requirement.max' => 'Shelf life requirement cannot exceed 365 days.',
            'buyers.required' => 'At least one buyer contact is required.',
            'buyers.min' => 'At least one buyer contact is required.',
            'buyers.*.name.required_with' => 'Buyer name is required.',
            'buyers.*.email.required_with' => 'Buyer email is required.',
            'buyers.*.email.email' => 'Buyer email must be a valid email address.',
            'accounts_payable.*.value.required_with' => 'Accounts payable contact value (email or URL) is required.',
            'other.*.email.email' => 'Other contact email must be a valid email address.',
            'vendor_guide.url' => 'Vendor guide must be a valid URL.',
            'broker.required' => 'Please select whether this customer uses a broker.',
            'broker_company_name.required' => 'Broker company name is required when using a broker.',
            'broker_commission.required' => 'Broker commission is required when using a broker.',
            'broker_commission.min' => 'Broker commission must be at least 0%.',
            'broker_commission.max' => 'Broker commission cannot exceed 100%.',
            'broker_contacts.required' => 'At least one broker contact is required when using a broker.',
            'broker_contacts.min' => 'At least one broker contact is required when using a broker.',
            'broker_contacts.*.name.required_with' => 'Broker contact name is required.',
            'broker_contacts.*.email.required_with' => 'Broker contact email is required.',
            'broker_contacts.*.email.email' => 'Broker contact email must be a valid email address.',
        ];

        $validator = Validator::make($data, $rules, $messages);

        // Custom validation for accounts_payable value (must be email or URL)
        $validator->after(function ($validator) use ($data) {
            if (! empty($data['accounts_payable'])) {
                foreach ($data['accounts_payable'] as $index => $ap) {
                    $value = $ap['value'] ?? '';
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL);
                    $isUrl = filter_var($value, FILTER_VALIDATE_URL);

                    if (! $isEmail && ! $isUrl) {
                        $validator->errors()->add(
                            "accounts_payable.{$index}.value",
                            'Accounts payable contact must be a valid email address or URL.'
                        );
                    }
                }
            }

            // Conditional broker validation - when broker is true
            $broker = $data['broker'] ?? false;
            if ($broker === true || $broker === 'true' || $broker === '1' || $broker === 1) {
                // Broker company name required
                if (empty($data['broker_company_name'])) {
                    $validator->errors()->add('broker_company_name', 'Broker company name is required when using a broker.');
                }

                // Broker commission required
                if (! isset($data['broker_commission']) || $data['broker_commission'] === '' || $data['broker_commission'] === null) {
                    $validator->errors()->add('broker_commission', 'Broker commission is required when using a broker.');
                }

                // At least one broker contact required
                if (empty($data['broker_contacts']) || count($data['broker_contacts']) === 0) {
                    $validator->errors()->add('broker_contacts', 'At least one broker contact is required when using a broker.');
                }
            }
        });

        return $validator;
    }

    /**
     * Transform request data to the format expected by FulfilService.
     */
    protected function transformRequestData(array $validated): array
    {
        // Ensure integer fields are properly cast
        $data = array_merge([
            'buyers' => [],
            'accounts_payable' => [],
            'other' => [],
            'broker_contacts' => [],
        ], $validated);

        // Cast IDs to integers for Fulfil API
        if (isset($data['sale_price_list'])) {
            $data['sale_price_list'] = (int) $data['sale_price_list'];
        }
        if (isset($data['customer_payment_term'])) {
            $data['customer_payment_term'] = (int) $data['customer_payment_term'];
        }
        if (isset($data['shipping_terms_category_id'])) {
            $data['shipping_terms_category_id'] = (int) $data['shipping_terms_category_id'];
        }
        if (isset($data['shelf_life_requirement'])) {
            $data['shelf_life_requirement'] = (int) $data['shelf_life_requirement'];
        }

        // Cast broker to boolean
        if (isset($data['broker'])) {
            $data['broker'] = filter_var($data['broker'], FILTER_VALIDATE_BOOLEAN);
        }

        // Cast broker_commission to float
        if (isset($data['broker_commission']) && $data['broker_commission'] !== '') {
            $data['broker_commission'] = (float) $data['broker_commission'];
        }

        return $data;
    }
}
