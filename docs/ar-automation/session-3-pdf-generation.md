# AR Automation - Session 3: PDF Generation

## Overview

This session implements the invoice PDF generation system using DOMPDF and Blade templates. The system fetches invoice data from Fulfil, transforms it into a DTO, and renders a professional invoice PDF.

## Prerequisites

- **Session 1: Data Foundation** must be completed first
  - Invoice fields for tracking created/modified dates
  - Customer fields for invoice preferences

- **Session 2: Customer & UI** must be completed first
  - UI buttons for PDF download and regenerate already exist as stubs
  - Located in `resources/js/Pages/ActiveCustomers/Show.tsx` (Outstanding Invoices section)
  - Stub handlers: `handleDownloadPdf()` and `handleRegeneratePdf()` show "coming soon" messages
  - These need to be connected to real routes/controllers in this session

## Scope

1. DOMPDF package installation and configuration
2. Invoice Data Transfer Object (DTO)
3. Blade template for invoice PDF
4. PDF generation service
5. PDF storage and retrieval

---

## 1. DOMPDF Setup

### Installation

```bash
composer require barryvdh/laravel-dompdf
```

### Configuration

Publish config if needed:
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Key configuration options in `config/dompdf.php`:
- Paper size: Letter (8.5" x 11")
- Enable remote images for logo
- Set appropriate font paths

---

## 2. PDF Generation Workflow

```
1. Get invoice data from Fulfil
       ↓
2. Transform data into InvoicePdfDto
       ↓
3. Pass DTO to Blade template
       ↓
4. Render HTML via Blade
       ↓
5. Convert HTML to PDF via DOMPDF
       ↓
6. Store PDF and return path
```

---

## 3. Invoice PDF Data Transfer Object

Create a DTO to structure the data passed to the Blade template.

### InvoicePdfDto

```php
class InvoicePdfDto
{
    // Invoice Core
    public string $number;
    public string $invoiceDate;
    public string $state;
    public ?string $reference;
    public float $totalAmount;
    public float $balance;
    public float $balanceDue;

    // Bill To Address
    public AddressDto $billToAddress;

    // Ship To Address
    public AddressDto $shipToAddress;
    public string $shipToCode;      // Warehouse name (e.g., "WHS05")
    public string $shipToName;      // Delivery party name

    // Order Info
    public string $salesPersonName;
    public string $paymentTermName;
    public string $orderNumber;

    // Line Items (filtered: account code !== '351')
    public Collection $lineItems;

    // Discount Lines (filtered: account code === '351')
    public Collection $discountLines;
}
```

### AddressDto

```php
class AddressDto
{
    public string $partyName;
    public string $street;
    public ?string $street2;
    public string $city;
    public string $subdivisionCode;  // State code (e.g., "NJ")
    public string $zip;
    public string $countryName;
}
```

### LineItemDto

```php
class LineItemDto
{
    public string $productCode;  // SKU
    public string $description;
    public float $quantity;
    public float $unitPrice;
    public float $amount;
}
```

---

## 4. Fulfil Data Mapping

### Primary Invoice Fields

| DTO Field | Fulfil Model | Fulfil Field |
|-----------|--------------|--------------|
| number | account.invoice | number (char, readonly) |
| invoiceDate | account.invoice | invoice_date (date) |
| state | account.invoice | state (selection: draft\|validated\|posted\|paid\|cancel) |
| reference | account.invoice | reference (char) |
| totalAmount | account.invoice | total_amount (decimal, readonly) |
| balance | account.invoice | balance (decimal, readonly) |
| balanceDue | account.invoice | balance_due (decimal, readonly) |

### Bill To Address

Resolved from `invoice.invoice_address` (Record ID → party.address)

| DTO Field | Fulfil Field |
|-----------|--------------|
| partyName | invoice_address.party.name |
| street | invoice_address.street |
| street2 | invoice_address.street2 |
| city | invoice_address.city |
| subdivisionCode | invoice_address.subdivision.code |
| zip | invoice_address.zip |
| countryName | invoice_address.country.name |

### Ship To Address

Resolved from first customer shipment: `invoice.customer_shipments[0]`

Model: `stock.shipment.out`

| DTO Field | Fulfil Field |
|-----------|--------------|
| shipToCode | shipment.warehouse.name |
| shipToName | shipment.delivery_address.party.name |
| (address fields) | shipment.delivery_address.* |

### Order Info

| DTO Field | Fulfil Source |
|-----------|---------------|
| salesPersonName | invoice.employee.party.name (company.employee) |
| paymentTermName | invoice.payment_term.name (account.invoice.payment_term) |
| orderNumber | invoice.origins (char, readonly) OR build from invoice.sales[0].number + invoice.reference |

### Line Items

From `invoice.lines` (account.invoice.line)

**Filter**: Include lines where `line.account.code !== '351'`

| DTO Field | Fulfil Field |
|-----------|--------------|
| productCode | line.product.code (from product.variant) |
| description | line.description |
| quantity | line.quantity |
| unitPrice | line.unit_price |
| amount | line.amount (readonly) |

### Discount Lines

From `invoice.lines` (account.invoice.line)

**Filter**: Include lines where `line.account.code === '351'` (Account 351 = "Off Invoice Discounts")

Same structure as line items, but amounts are typically negative.

---

## 5. Blade Template

The invoice PDF uses a Blade template. Store at: `resources/views/pdf/invoice.blade.php`

### Template Variables

The template expects these variables:

```php
return view('pdf.invoice', [
    'invoice' => $invoicePdfDto,
    'invoice_address' => $invoicePdfDto->billToAddress,
    'ship_to_address' => $invoicePdfDto->shipToAddress,
    'ship_to_code' => $invoicePdfDto->shipToCode,
    'ship_to_name' => $invoicePdfDto->shipToName,
    'employee' => (object)['party' => (object)['name' => $invoicePdfDto->salesPersonName]],
    'payment_term' => (object)['name' => $invoicePdfDto->paymentTermName],
    'order_number' => $invoicePdfDto->orderNumber,
    'line_items' => $invoicePdfDto->lineItems,
    'discount_lines' => $invoicePdfDto->discountLines,
]);
```

### Full Blade Template

```blade
{{--
UNIVERSAL YUMS - INVOICE BLADE TEMPLATE

DATA SOURCE: Fulfil ERP
MODELS:      account.invoice, account.invoice.line,
             stock.shipment.out (customer_shipment)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->number }}</title>
    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }

        /* Page Layout (optimized for PDF / print) */
        .invoice-page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            padding: 0.6in 0.75in;
            position: relative;
        }

        @media print {
            .invoice-page {
                padding: 0.4in 0.6in;
            }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .header-left {
            flex: 1;
        }

        .logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 14px;
        }

        .company-address {
            font-size: 9pt;
            line-height: 1.5;
            color: #444;
        }

        .company-phone {
            font-size: 9pt;
            color: #444;
            margin-top: 6px;
        }

        .header-right {
            text-align: right;
        }

        .invoice-title {
            font-size: 26pt;
            font-weight: 300;
            color: #333;
            margin-bottom: 10px;
        }

        .invoice-meta {
            font-size: 9pt;
            line-height: 1.8;
        }

        .invoice-meta .label {
            display: inline-block;
            width: 70px;
            font-weight: 600;
            text-align: left;
        }

        /* Address Block */
        .address-row {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border: 1px solid #ccc;
        }

        .address-block {
            flex: 1;
            padding: 10px 14px;
            font-size: 9pt;
            line-height: 1.6;
        }

        .address-block + .address-block {
            border-left: 1px solid #ccc;
        }

        .address-label {
            font-weight: 700;
            font-size: 8.5pt;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 4px;
        }

        .address-name {
            font-weight: 600;
        }

        /* Order Info Row */
        .order-info {
            display: flex;
            border: 1px solid #ccc;
            margin-bottom: 20px;
            font-size: 9pt;
        }

        .order-info-cell {
            flex: 1;
            padding: 8px 14px;
        }

        .order-info-cell + .order-info-cell {
            border-left: 1px solid #ccc;
        }

        .order-info-cell .label {
            font-weight: 700;
            font-size: 8.5pt;
            color: #555;
            margin-bottom: 2px;
        }

        /* Line Items Table */
        .line-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 9pt;
        }

        .line-items thead th {
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 8.5pt;
            color: #555;
        }

        .line-items thead th.num {
            width: 30px;
            text-align: center;
        }

        .line-items thead th.item-code {
            width: 80px;
        }

        .line-items thead th.qty,
        .line-items thead th.unit-price,
        .line-items thead th.amount {
            text-align: right;
            width: 80px;
        }

        .line-items tbody td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            vertical-align: top;
        }

        .line-items tbody td.num {
            text-align: center;
        }

        .line-items tbody td.qty,
        .line-items tbody td.unit-price,
        .line-items tbody td.amount {
            text-align: right;
            white-space: nowrap;
        }

        /* Totals */
        .totals-wrapper {
            display: flex;
            justify-content: flex-end;
        }

        .totals-table {
            width: 280px;
            border-collapse: collapse;
            font-size: 9.5pt;
        }

        .totals-table td {
            padding: 6px 10px;
            border: 1px solid #ccc;
        }

        .totals-table .totals-label {
            text-align: right;
            font-weight: 600;
            color: #555;
            width: 140px;
        }

        .totals-table .totals-value {
            text-align: right;
            width: 140px;
        }

        .totals-table .total-row td {
            font-size: 14pt;
            font-weight: 700;
            padding: 10px;
            color: #222;
        }

        .totals-table .balance-due td {
            font-weight: 700;
            color: #222;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 0.6in;
            left: 0.75in;
            right: 0.75in;
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="invoice-page">

        {{-- Header (hardcoded Universal Yums) --}}
        <div class="header">
            <div class="header-left">
                <img src="{{ public_path('images/universal-yums-logo.png') }}" alt="Universal Yums" class="logo">
                <div class="company-address">
                    9 WOODLAND RD UNIT B<br>
                    ROSELAND NJ 07068<br>
                    UNITED STATES
                </div>
                <div class="company-phone">
                    Phone: 9732877393
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-meta">
                    <span class="label">Invoice #</span> {{ $invoice->number }}<br>
                    <span class="label">Date:</span> {{ \Carbon\Carbon::parse($invoice->invoiceDate)->format('M d, Y') }}<br>
                    <span class="label">State:</span> {{ ucfirst($invoice->state) }}
                </div>
            </div>
        </div>

        {{-- Bill To / Ship To --}}
        <div class="address-row">
            <div class="address-block">
                <div class="address-label">Bill To</div>
                <div class="address-name">{{ $invoice_address->partyName }}</div>
                <div>{{ $invoice_address->street }}</div>
                @if(!empty($invoice_address->street2))
                    <div>{{ $invoice_address->street2 }}</div>
                @endif
                <div>{{ $invoice_address->city }} {{ $invoice_address->subdivisionCode }}</div>
                <div>{{ $invoice_address->zip }}</div>
                <div>{{ $invoice_address->countryName }}</div>
            </div>
            <div class="address-block">
                <div class="address-label">Ship To</div>
                <div class="address-name">{{ $ship_to_code }}</div>
                <div>{{ $ship_to_name }}</div>
                <div>{{ $ship_to_address->street }}</div>
                @if(!empty($ship_to_address->street2))
                    <div>{{ $ship_to_address->street2 }}</div>
                @endif
                <div>{{ $ship_to_address->city }}, {{ $ship_to_address->subdivisionCode }}</div>
                <div>{{ $ship_to_address->zip }} {{ $ship_to_address->countryName }}</div>
            </div>
        </div>

        {{-- Order Info --}}
        <div class="order-info">
            <div class="order-info-cell">
                <div class="label">Sales Person</div>
                <div>{{ $employee->party->name }}</div>
            </div>
            <div class="order-info-cell">
                <div class="label">Payment Terms</div>
                <div>{{ $payment_term->name }}</div>
            </div>
            <div class="order-info-cell">
                <div class="label">Reference</div>
                <div>{{ $invoice->reference }}</div>
            </div>
            <div class="order-info-cell">
                <div class="label">Order</div>
                <div>{{ $order_number }}</div>
            </div>
        </div>

        {{-- Line Items --}}
        <table class="line-items">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th class="item-code">Item</th>
                    <th>Description</th>
                    <th class="qty">Quantity</th>
                    <th class="unit-price">Unit Price</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($line_items as $index => $line)
                    <tr>
                        <td class="num">{{ $index + 1 }}</td>
                        <td>{{ $line->productCode }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="qty">{{ number_format($line->quantity) }}</td>
                        <td class="unit-price">${{ number_format($line->unitPrice, 2) }}</td>
                        <td class="amount">${{ number_format($line->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        @php
            $display_subtotal = $line_items->sum('amount');
            $discount_total = $discount_lines->sum('amount');
            $discount_percent = ($display_subtotal != 0)
                ? round(abs($discount_total) / $display_subtotal * 100)
                : 0;
            $amount_paid = $invoice->totalAmount - $invoice->balance;
        @endphp

        <div class="totals-wrapper">
            <table class="totals-table">
                <tr>
                    <td class="totals-label">Subtotal</td>
                    <td class="totals-value">${{ number_format($display_subtotal, 2) }}</td>
                </tr>
                @if($discount_lines->isNotEmpty())
                    <tr>
                        <td class="totals-label">Discounts ({{ $discount_percent }}%)</td>
                        <td class="totals-value">${{ number_format(abs($discount_total), 2) }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td class="totals-label">Total</td>
                    <td class="totals-value">${{ number_format($invoice->totalAmount, 2) }}</td>
                </tr>
                <tr>
                    <td class="totals-label">Paid</td>
                    <td class="totals-value">${{ number_format($amount_paid, 2) }}</td>
                </tr>
                <tr>
                    <td class="totals-label">Balance</td>
                    <td class="totals-value">${{ number_format($invoice->balance, 2) }}</td>
                </tr>
                <tr class="balance-due">
                    <td class="totals-label">Balance Due</td>
                    <td class="totals-value">${{ number_format($invoice->balanceDue, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <span>Universal Yums</span>
            <span>{{ $current_page ?? 1 }}/{{ $total_pages ?? 1 }}</span>
        </div>

    </div>
</body>
</html>
```

---

## 6. PDF Generation Service

Create a service class to handle PDF generation.

### InvoicePdfService

```php
class InvoicePdfService
{
    /**
     * Generate PDF for an invoice
     *
     * @param int $invoiceId Fulfil invoice ID
     * @param bool $forceRegenerate Skip cache and regenerate
     * @return string Path to generated PDF
     */
    public function generate(int $invoiceId, bool $forceRegenerate = false): string;

    /**
     * Get or generate PDF (checks for existing first)
     */
    public function getOrGenerate(int $invoiceId): string;

    /**
     * Build DTO from Fulfil invoice data
     */
    protected function buildDto(array $fulfilData): InvoicePdfDto;

    /**
     * Render PDF from DTO
     */
    protected function renderPdf(InvoicePdfDto $dto): \Barryvdh\DomPDF\PDF;

    /**
     * Store PDF to disk
     */
    protected function storePdf(\Barryvdh\DomPDF\PDF $pdf, string $invoiceNumber): string;
}
```

### Storage Location

Store generated PDFs in: `storage/app/invoices/{invoice_number}.pdf`

Consider organizing by year/month for large volumes:
`storage/app/invoices/2024/03/{invoice_number}.pdf`

---

## 7. Customer SKU Substitution

When generating invoice PDFs for customers who require their SKUs:

### Logic

```php
if ($customer->invoice_requires_customer_skus) {
    foreach ($lineItems as $line) {
        $mapping = CustomerSku::where('customer_id', $customer->id)
            ->where('yums_sku', $line->productCode)
            ->first();

        if ($mapping) {
            $line->productCode = $mapping->customer_sku;
        }
    }
}
```

### Important

- SKU substitution only affects PDF display
- Original Yums SKU is preserved in Fulfil
- If any SKU lacks mapping, the automation should halt (handled in Session 5)

---

## 8. Logo Asset

Ensure the Universal Yums logo is available at:
`public/images/universal-yums-logo.png`

The template references this path for the header logo.

---

## Deliverables Checklist

- [ ] Install barryvdh/laravel-dompdf package
- [ ] Configure DOMPDF (paper size, fonts, remote images)
- [ ] Create InvoicePdfDto class
- [ ] Create AddressDto class
- [ ] Create LineItemDto class
- [ ] Create Blade template at resources/views/pdf/invoice.blade.php
- [ ] Create InvoicePdfService class
- [ ] Implement Fulfil data fetching for invoice details
- [ ] Implement DTO transformation from Fulfil data
- [ ] Implement PDF rendering
- [ ] Implement PDF storage
- [ ] Implement customer SKU substitution in DTO building
- [ ] Add logo asset to public/images/
- [ ] Create route/controller for PDF download
- [ ] Create route/controller for PDF regeneration
- [ ] Connect existing stub buttons in Show.tsx to the new routes:
  - `handleDownloadPdf()` calls `/invoices/{id}/pdf/download`
  - `handleRegeneratePdf()` calls `/invoices/{id}/pdf/regenerate`

---

## Dependencies

- Session 1: Data Foundation (customer fields, invoice fields)

## Blocks

- Session 4: Email Infrastructure (attaches PDFs to emails)
- Session 5: Automation & Polling (generates PDFs automatically)
