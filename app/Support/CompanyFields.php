<?php

namespace App\Support;

use Illuminate\Validation\Validator;

/**
 * Single source of truth for company field definitions shared between
 * Active Customers and Prospects.
 *
 * When you add a new field to a company, add it here and it will
 * automatically be available in both CustomerController and
 * ProspectController validation, as well as the promote() flow.
 */
class CompanyFields
{
    // ──────────────────────────────────────────────
    //  Validation Rules
    // ──────────────────────────────────────────────

    /**
     * Shared validation rules for fields common to both customers and prospects.
     *
     * @param  bool  $required  Enforce required fields (customer create & promote).
     * @param  bool  $sometimes  Prefix with 'sometimes' (updates - only validate if present).
     */
    public static function sharedRules(bool $required = false, bool $sometimes = false): array
    {
        // "required" for customer create, "sometimes" for updates, "nullable" for prospect create
        $req = $required ? 'required' : ($sometimes ? 'sometimes' : 'nullable');
        $reqOrNullable = $required ? 'required' : 'nullable';

        return array_merge(
            self::coreFieldRules($req, $reqOrNullable),
            self::contactRules($required, $sometimes),
        );
    }

    /**
     * Core non-contact, non-commercial-term fields.
     */
    private static function coreFieldRules(string $req, string $reqOrNullable): array
    {
        return [
            'shelf_life_requirement' => [$reqOrNullable, 'integer', 'min:30', 'max:365'],
            'vendor_guide' => ['nullable', 'url', 'max:500'],
            'broker' => [$reqOrNullable, 'boolean'],
            'broker_company_name' => ['nullable', 'string', 'max:255'],
            'broker_commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ar_edi' => ['sometimes', 'boolean'],
            'ar_consolidated_invoicing' => ['sometimes', 'boolean'],
            'ar_requires_customer_skus' => ['sometimes', 'boolean'],
            'ar_invoice_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Contact array validation rules (buyers, AP, other, broker contacts).
     */
    private static function contactRules(bool $required, bool $sometimes): array
    {
        $presence = $required ? 'required' : ($sometimes ? 'sometimes' : 'nullable');
        $minRule = $required ? 'min:1' : 'min:0';

        return [
            // Broker contacts
            'broker_contacts' => ['nullable', 'array'],
            'broker_contacts.*.name' => ['required_with:broker_contacts', 'string', 'min:2', 'max:100'],
            'broker_contacts.*.email' => ['required_with:broker_contacts', 'email', 'max:255'],

            // Buyers
            'buyers' => [$presence, 'array', $minRule],
            'buyers.*.name' => ['required_with:buyers', 'string', 'min:2', 'max:100'],
            'buyers.*.email' => ['required_with:buyers', 'email', 'max:255'],

            // Accounts Payable
            'accounts_payable' => [$presence, 'array', $minRule],
            'accounts_payable.*.name' => ['required_with:accounts_payable', 'string', 'min:2', 'max:100'],
            'accounts_payable.*.value' => ['required_with:accounts_payable', 'string', 'max:500'],

            // Other contacts
            'other' => ['nullable', 'array'],
            'other.*.name' => ['required_with:other', 'string', 'min:2', 'max:100'],
            'other.*.email' => ['required_with:other', 'email', 'max:255'],
            'other.*.function' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Commercial term rules for Active Customers (Fulfil IDs).
     */
    public static function customerCommercialRules(bool $required = false): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'sale_price_list' => [$req, 'integer'],
            'customer_payment_term' => [$req, 'integer'],
            'shipping_terms_category_id' => [$req, 'integer'],
        ];
    }

    /**
     * Commercial term rules for Prospects (stored as local values).
     */
    public static function prospectCommercialRules(bool $isSometimes = false): array
    {
        $prefix = $isSometimes ? 'sometimes' : 'nullable';

        return [
            'discount_percent' => [$prefix, 'numeric', 'min:0', 'max:100'],
            'payment_terms' => [$prefix, 'string', 'max:255'],
            'shipping_terms' => [$prefix, 'string', 'max:255'],
        ];
    }

    /**
     * Fields unique to Prospects (not on Active Customers).
     */
    public static function prospectOnlyRules(bool $isSometimes = false): array
    {
        $prefix = $isSometimes ? 'sometimes' : 'nullable';

        return [
            'status' => [$isSometimes ? 'sometimes' : 'nullable', 'in:target,contacted,engaged,dormant,active'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'company_urls' => [$prefix, 'array'],
            'company_urls.*' => ['string', 'max:255'],
            'customer_type' => ['nullable', 'string', 'max:255'],
            'uncategorized' => [$isSometimes ? 'sometimes' : 'nullable', 'array'],
            'uncategorized.*.name' => ['required_with:uncategorized', 'string', 'min:1', 'max:100'],
            'uncategorized.*.value' => ['nullable', 'email', 'max:255'],
            'product_ids' => [$prefix, 'array'],
            'product_ids.*' => ['integer'],
        ];
    }

    // ──────────────────────────────────────────────
    //  Validation Messages
    // ──────────────────────────────────────────────

    /**
     * Shared validation messages for both customers and prospects.
     */
    public static function messages(): array
    {
        return [
            // Company name
            'name.required' => 'Company name is required.',
            'name.min' => 'Company name must be at least 2 characters.',
            'company_name.required' => 'Company name is required.',
            'company_name.min' => 'Company name must be at least 2 characters.',

            // Commercial terms (customer)
            'sale_price_list.required' => 'Please select a discount level.',
            'customer_payment_term.required' => 'Please select payment terms.',
            'shipping_terms_category_id.required' => 'Please select shipping terms.',

            // Requirements
            'shelf_life_requirement.required' => 'Shelf life requirement is required.',
            'shelf_life_requirement.min' => 'Shelf life requirement must be at least 30 days.',
            'shelf_life_requirement.max' => 'Shelf life requirement cannot exceed 365 days.',
            'vendor_guide.url' => 'Vendor guide must be a valid URL.',

            // Buyers
            'buyers.required' => 'At least one buyer contact is required.',
            'buyers.min' => 'At least one buyer contact is required.',
            'buyers.*.name.required_with' => 'Buyer name is required.',
            'buyers.*.email.required_with' => 'Buyer email is required.',
            'buyers.*.email.email' => 'Buyer email must be a valid email address.',

            // Accounts Payable
            'accounts_payable.required' => 'At least one accounts payable contact is required.',
            'accounts_payable.min' => 'At least one accounts payable contact is required.',
            'accounts_payable.*.name.required_with' => 'AP contact name is required.',
            'accounts_payable.*.value.required_with' => 'Accounts payable contact value (email or URL) is required.',

            // Other contacts
            'other.*.name.required_with' => 'Other contact name is required.',
            'other.*.email.email' => 'Other contact email must be a valid email address.',

            // Broker
            'broker.required' => 'Please select whether this customer uses a broker.',
            'broker_commission.min' => 'Broker commission must be at least 0%.',
            'broker_commission.max' => 'Broker commission cannot exceed 100%.',
            'broker_contacts.*.name.required_with' => 'Broker contact name is required.',
            'broker_contacts.*.email.required_with' => 'Broker contact email is required.',
            'broker_contacts.*.email.email' => 'Broker contact email must be a valid email address.',

            // Prospect-only
            'uncategorized.*.name.required_with' => 'Contact name is required.',
            'uncategorized.*.value.email' => 'Please enter a valid email address.',
        ];
    }

    // ──────────────────────────────────────────────
    //  Sanitization & Hooks
    // ──────────────────────────────────────────────

    /**
     * Strip out blank placeholder entries from contact arrays.
     *
     * The frontend initialises empty contact rows for the user to type into.
     * These must be removed before validation.
     */
    public static function sanitizeContacts(array $data): array
    {
        $contactFields = [
            'buyers' => 'name',
            'accounts_payable' => 'name',
            'other' => 'name',
            'broker_contacts' => 'name',
            'uncategorized' => 'name',
        ];

        foreach ($contactFields as $field => $keyField) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_values(array_filter(
                    $data[$field],
                    fn ($entry) => ! empty(trim($entry[$keyField] ?? ''))
                ));
            }
        }

        return $data;
    }

    /**
     * Register shared after-validation hooks.
     *
     * - AP contacts: value must be a valid email or URL.
     * - Broker conditional: when broker=true, company name, commission,
     *   and at least one broker contact are required.
     */
    public static function addAfterValidation(Validator $validator, array $data): void
    {
        // AP value must be email or URL
        if (! empty($data['accounts_payable'])) {
            foreach ($data['accounts_payable'] as $index => $ap) {
                $value = $ap['value'] ?? '';
                if ($value === '') {
                    continue;
                }
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

        // Conditional broker validation
        $broker = $data['broker'] ?? false;
        if ($broker === true || $broker === 'true' || $broker === '1' || $broker === 1) {
            if (empty($data['broker_company_name'])) {
                $validator->errors()->add('broker_company_name', 'Broker company name is required when using a broker.');
            }
            if (! isset($data['broker_commission']) || $data['broker_commission'] === '' || $data['broker_commission'] === null) {
                $validator->errors()->add('broker_commission', 'Broker commission is required when using a broker.');
            }
            if (empty($data['broker_contacts']) || count($data['broker_contacts']) === 0) {
                $validator->errors()->add('broker_contacts', 'At least one broker contact is required when using a broker.');
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Data Transformation
    // ──────────────────────────────────────────────

    /**
     * Cast common fields to their proper types for API / DB consumption.
     */
    public static function castTypes(array $data): array
    {
        $intFields = ['sale_price_list', 'customer_payment_term', 'shipping_terms_category_id', 'shelf_life_requirement'];
        foreach ($intFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        if (isset($data['broker'])) {
            $data['broker'] = filter_var($data['broker'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data['broker_commission']) && $data['broker_commission'] !== '') {
            $data['broker_commission'] = (float) $data['broker_commission'];
        }

        if (isset($data['ar_invoice_discount']) && $data['ar_invoice_discount'] !== '' && $data['ar_invoice_discount'] !== null) {
            $data['ar_invoice_discount'] = (float) $data['ar_invoice_discount'];
        }

        return $data;
    }
}
