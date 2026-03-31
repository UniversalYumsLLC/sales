<?php

namespace App\Http\Controllers;

use App\Exceptions\FulfilUnavailableException;
use App\Jobs\SyncGmailForDomains;
use App\Models\LocalCustomerMetadata;
use App\Services\FulfilService;
use App\Support\CompanyFields;
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
     *
     * Creates the customer in Fulfil, then saves local-only fields
     * (broker, AR settings, vendor_guide) to local_customer_metadata.
     * Only invoice_discount is sent to Fulfil metafields.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = $this->validateCustomerData(CompanyFields::sanitizeInput($request->all()), isCreate: true);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = CompanyFields::castTypes($validator->validated());

        // Extract local-only fields before sending to Fulfil
        $localFields = [];
        $localFieldKeys = [
            'broker', 'broker_commission', 'broker_company_name',
            'vendor_guide',
            'ar_edi', 'ar_consolidated_invoicing', 'ar_requires_customer_skus',
        ];
        foreach ($localFieldKeys as $key) {
            if (array_key_exists($key, $data)) {
                $localFields[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        try {
            $customer = $this->fulfil->createCustomer($data);

            // Extract domains from buyer emails for Gmail sync
            $domains = $this->extractDomainsFromContacts($data);

            // Dispatch Gmail sync job for the new customer's domains (runs in background)
            if (! empty($domains) && isset($customer['id'])) {
                SyncGmailForDomains::dispatch($domains, 'customer', $customer['id']);
            }

            if (isset($customer['id'])) {
                // Create local metadata with local-only fields
                LocalCustomerMetadata::create(array_merge(
                    ['fulfil_party_id' => $customer['id']],
                    $localFields,
                ));

                // Save invoice_discount to Fulfil metafields (only AR field still in Fulfil)
                if (isset($data['ar_invoice_discount']) && $data['ar_invoice_discount'] !== '' && $data['ar_invoice_discount'] !== null) {
                    try {
                        $this->fulfil->updateCustomerArSettings($customer['id'], [
                            'invoice_discount' => (float) $data['ar_invoice_discount'],
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to save invoice discount for new customer', [
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
        } catch (FulfilUnavailableException $e) {
            return back()
                ->withErrors(['general' => $e->getUserMessage()])
                ->withInput();
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
     *
     * Local fields (broker, broker_commission, vendor_guide, ar_edi,
     * ar_consolidated_invoicing, ar_requires_customer_skus) are saved to
     * local_customer_metadata. Remaining fields go to Fulfil.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = $this->validateCustomerData(CompanyFields::sanitizeInput($request->all()), isCreate: false);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = CompanyFields::castTypes($validator->validated());

        // Extract local-only fields from the data before sending to Fulfil
        $localFields = [];
        $localFieldKeys = [
            'broker', 'broker_commission', 'broker_company_name',
            'vendor_guide',
            'ar_edi', 'ar_consolidated_invoicing', 'ar_requires_customer_skus',
        ];
        foreach ($localFieldKeys as $key) {
            if (array_key_exists($key, $data)) {
                $localFields[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        try {
            // Save local fields to local_customer_metadata
            if (! empty($localFields)) {
                $metadata = LocalCustomerMetadata::findOrCreateForCustomer($id);
                $metadata->update($localFields);
            }

            // Send remaining fields to Fulfil (name, price list, payment terms,
            // shipping terms, shelf_life, ar_invoice_discount, contacts, etc.)
            $customer = $this->fulfil->updateCustomer($id, $data);

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer,
            ]);
        } catch (FulfilUnavailableException $e) {
            return response()->json([
                'message' => $e->getUserMessage(),
            ], 503);
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
     *
     * Rules are sourced from CompanyFields (single source of truth) plus
     * the customer-specific company name rule.
     */
    protected function validateCustomerData(array $data, bool $isCreate): \Illuminate\Validation\Validator
    {
        $rules = array_merge(
            ['name' => [$isCreate ? 'required' : 'sometimes', 'string', 'min:2', 'max:255']],
            CompanyFields::customerCommercialRules(required: $isCreate),
            CompanyFields::sharedRules(required: $isCreate, sometimes: ! $isCreate),
        );

        $validator = Validator::make($data, $rules, CompanyFields::messages());

        $validator->after(function ($validator) use ($data) {
            CompanyFields::addAfterValidation($validator, $data);
        });

        return $validator;
    }
}
