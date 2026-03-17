<?php

namespace App\DTOs\Invoice;

use Illuminate\Support\Collection;

class InvoicePdfDto
{
    public function __construct(
        // Invoice Core
        public string $number,
        public string $invoiceDate,
        public string $state,
        public ?string $reference,
        public float $totalAmount,
        public float $balance,
        public float $balanceDue,

        // Bill To Address
        public AddressDto $billToAddress,

        // Ship To Address
        public AddressDto $shipToAddress,
        public string $shipToCode,
        public string $shipToName,

        // Order Info
        public string $salesPersonName,
        public string $paymentTermName,
        public string $orderNumber,

        // Line Items (filtered: account code !== '351')
        public Collection $lineItems,

        // Discount Lines (filtered: account code === '351')
        public Collection $discountLines,

        // Customer ID (for SKU mapping lookup)
        public int $customerId,
    ) {}

    /**
     * Create from Fulfil invoice data (after transformation).
     */
    public static function fromFulfil(array $data): self
    {
        // Build bill-to address
        $billToAddress = isset($data['invoice_address'])
            ? AddressDto::fromFulfil($data['invoice_address'])
            : AddressDto::empty();

        // Build ship-to address from first customer shipment
        $shipToAddress = AddressDto::empty();
        $shipToCode = '';
        $shipToName = '';

        if (! empty($data['customer_shipments'][0])) {
            $shipment = $data['customer_shipments'][0];
            $shipToCode = $shipment['warehouse_name'] ?? '';
            $shipToName = $shipment['delivery_party_name'] ?? '';

            if (isset($shipment['delivery_address'])) {
                $shipToAddress = AddressDto::fromFulfil($shipment['delivery_address']);
            }
        }

        // Separate line items from discount lines (account code 351)
        $allLines = collect($data['lines'] ?? []);

        $lineItems = $allLines
            ->filter(fn ($line) => ($line['account_code'] ?? '') !== '351')
            ->map(fn ($line) => LineItemDto::fromFulfil($line))
            ->values();

        $discountLines = $allLines
            ->filter(fn ($line) => ($line['account_code'] ?? '') === '351')
            ->map(fn ($line) => LineItemDto::fromFulfil($line))
            ->values();

        return new self(
            number: $data['number'] ?? '',
            invoiceDate: $data['invoice_date'] ?? '',
            state: $data['state'] ?? '',
            reference: $data['reference'] ?? null,
            totalAmount: (float) ($data['total_amount'] ?? 0),
            balance: (float) ($data['balance'] ?? 0),
            balanceDue: (float) ($data['balance_due'] ?? $data['balance'] ?? 0),
            billToAddress: $billToAddress,
            shipToAddress: $shipToAddress,
            shipToCode: $shipToCode,
            shipToName: $shipToName,
            salesPersonName: $data['sales_person_name'] ?? '',
            paymentTermName: $data['payment_term_name'] ?? '',
            orderNumber: $data['order_number'] ?? $data['origins'] ?? '',
            lineItems: $lineItems,
            discountLines: $discountLines,
            customerId: (int) ($data['party_id'] ?? 0),
        );
    }

    /**
     * Get all unique product codes from line items (for SKU mapping validation).
     */
    public function getProductCodes(): array
    {
        return $this->lineItems
            ->pluck('productCode')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Apply customer SKU mappings to line items.
     *
     * @param  array<string, string>  $skuMap  [yums_sku => customer_sku]
     */
    public function withCustomerSkus(array $skuMap): self
    {
        $mappedLineItems = $this->lineItems->map(function (LineItemDto $line) use ($skuMap) {
            if (isset($skuMap[$line->productCode])) {
                return $line->withCustomerSku($skuMap[$line->productCode]);
            }

            return $line;
        });

        return new self(
            number: $this->number,
            invoiceDate: $this->invoiceDate,
            state: $this->state,
            reference: $this->reference,
            totalAmount: $this->totalAmount,
            balance: $this->balance,
            balanceDue: $this->balanceDue,
            billToAddress: $this->billToAddress,
            shipToAddress: $this->shipToAddress,
            shipToCode: $this->shipToCode,
            shipToName: $this->shipToName,
            salesPersonName: $this->salesPersonName,
            paymentTermName: $this->paymentTermName,
            orderNumber: $this->orderNumber,
            lineItems: $mappedLineItems,
            discountLines: $this->discountLines,
            customerId: $this->customerId,
        );
    }
}
