{{--
UNIVERSAL YUMS - INVOICE BLADE TEMPLATE

DATA SOURCE: Fulfil ERP
NOTE: Uses @page margins for consistent 0.5" margins on all sides
      All tables use 100% width to fill the content area consistently
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $invoice->number }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 36pt;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #333;
            line-height: 1.3;
            padding: 36pt;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td, th {
            vertical-align: top;
        }

        /* Header */
        .header {
            margin-bottom: 15px;
        }

        .header td {
            vertical-align: top;
        }

        .header-left {
            width: 60%;
        }

        .header-right {
            width: 40%;
        }

        .logo {
            max-width: 140px;
            height: auto;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .company-info {
            font-size: 8pt;
            line-height: 1.4;
            color: #444;
        }

        .invoice-title {
            font-size: 20pt;
            font-weight: 300;
            color: #333;
            text-align: right;
            margin-bottom: 8px;
        }

        .invoice-meta {
            font-size: 9pt;
            line-height: 1.6;
            text-align: right;
        }

        .invoice-meta strong {
            font-weight: 600;
        }

        /* Address Blocks */
        .addresses {
            border: 1px solid #bbb;
            margin-bottom: 15px;
        }

        .addresses td {
            width: 50%;
            padding: 8px 10px;
            font-size: 8pt;
            line-height: 1.5;
        }

        .addresses td + td {
            border-left: 1px solid #bbb;
        }

        .addr-label {
            font-weight: 700;
            font-size: 7pt;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 3px;
        }

        .addr-name {
            font-weight: 600;
        }

        /* Order Info */
        .order-info {
            border: 1px solid #bbb;
            margin-bottom: 15px;
        }

        .order-info td {
            width: 50%;
            padding: 6px 10px;
            font-size: 8pt;
        }

        .order-info td + td {
            border-left: 1px solid #bbb;
        }

        .order-info .lbl {
            font-weight: 700;
            font-size: 7pt;
            color: #555;
        }

        /* Line Items */
        .items {
            margin-bottom: 15px;
            font-size: 8pt;
            border: 1px solid #bbb;
        }

        .items th {
            background-color: #f0f0f0;
            border: 1px solid #bbb;
            padding: 6px 8px;
            text-align: left;
            font-weight: 700;
            font-size: 7pt;
            color: #555;
        }

        .items td {
            border: 1px solid #bbb;
            padding: 6px 8px;
        }

        .items .c { text-align: center; }
        .items .r { text-align: right; }

        .col-num { width: 5%; }
        .col-sku { width: 18%; }
        .col-retailer-sku { width: 12%; }
        .col-product { width: 34%; }
        .col-qty { width: 10%; }
        .col-price { width: 9%; }
        .col-amt { width: 12%; }

        /* Without retailer SKU column, product gets the extra width */
        .col-product-wide { width: 46%; }

        /* Totals */
        .totals-wrapper {
            margin-bottom: 15px;
        }

        .totals-spacer {
            width: 68%;
        }

        .totals-cell {
            width: 32%;
        }

        .totals {
            width: 100%;
            font-size: 8pt;
            border: 1px solid #bbb;
        }

        .totals td {
            padding: 4px 8px;
        }

        .totals tr + tr td {
            border-top: 1px solid #bbb;
        }

        .totals .lbl {
            text-align: right;
            font-weight: 600;
            color: #555;
            width: 50%;
        }

        .totals .val {
            text-align: right;
            width: 50%;
        }

        .totals .total-main td {
            font-weight: 700;
            color: #222;
        }

        .totals .balance-due td {
            font-weight: 700;
            color: #222;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            font-size: 7pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        .footer td {
            padding: 0;
        }

        .footer .r {
            text-align: right;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <table class="header">
        <tr>
            <td class="header-left">
                <img src="{{ public_path('images/universal_yums_logo.svg') }}" alt="Universal Yums" class="logo">
                <div class="company-info">
                    9 WOODLAND RD UNIT B<br>
                    ROSELAND NJ 07068<br>
                    UNITED STATES<br>
                    Phone: 9732877393
                </div>
            </td>
            <td class="header-right">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-meta">
                    <strong>Invoice #:</strong> {{ $invoice->number }}<br>
                    <strong>Date Issued:</strong> {{ \Carbon\Carbon::parse($invoice->invoiceDate)->format('M d, Y') }}<br>
                    @if($invoice->dueDate)
                        <strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->dueDate)->format('M d, Y') }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Bill To / Ship To --}}
    <table class="addresses">
        <tr>
            <td>
                <div class="addr-label">Bill To</div>
                <div class="addr-name">{{ $invoice_address->partyName }}</div>
                {{ $invoice_address->street }}<br>
                @if(!empty($invoice_address->street2))
                    {{ $invoice_address->street2 }}<br>
                @endif
                {{ $invoice_address->city }} {{ $invoice_address->subdivisionCode }}<br>
                {{ $invoice_address->zip }}<br>
                {{ $invoice_address->countryName }}
            </td>
            <td>
                <div class="addr-label">Ship To</div>
                <div class="addr-name">{{ $ship_to_code }}</div>
                {{ $ship_to_name }}<br>
                @if($ship_to_address)
                    {{ $ship_to_address->street }}<br>
                    @if(!empty($ship_to_address->street2))
                        {{ $ship_to_address->street2 }}<br>
                    @endif
                    {{ $ship_to_address->city }}, {{ $ship_to_address->subdivisionCode }}<br>
                    {{ $ship_to_address->zip }} {{ $ship_to_address->countryName }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Order Info --}}
    <table class="order-info">
        <tr>
            <td>
                <div class="lbl">Payment Terms</div>
                {{ $payment_term->name ?? '-' }}
            </td>
            <td>
                <div class="lbl">Reference PO</div>
                {{ $invoice->reference ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- Line Items --}}
    <table class="items">
        <thead>
            <tr>
                <th class="col-num c">#</th>
                <th class="col-sku">SKU</th>
                @if($has_customer_skus)
                    <th class="col-retailer-sku">Retailer SKU</th>
                    <th class="col-product">Product</th>
                @else
                    <th class="col-product-wide">Product</th>
                @endif
                <th class="col-qty r">Unit Qty</th>
                <th class="col-price r">Unit Price</th>
                <th class="col-amt r">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($line_items as $index => $line)
                @php
                    // Strip "[SKU] " prefix from description to get just the product name
                    $productName = preg_replace('/^\[.*?\]\s*/', '', $line->description);
                @endphp
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td>{{ $line->productCode }}</td>
                    @if($has_customer_skus)
                        <td>{{ $line->customerSku ?? '-' }}</td>
                    @endif
                    <td>{{ $productName }}</td>
                    <td class="r">{{ number_format($line->quantity) }}</td>
                    <td class="r">${{ number_format($line->unitPrice, 2) }}</td>
                    <td class="r">${{ number_format($line->amount, 2) }}</td>
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

        // Apply invoice-level discount if set (percentage off subtotal)
        $invoice_discount_amount = 0;
        if ($invoice_discount !== null && $invoice_discount > 0) {
            $invoice_discount_amount = round($display_subtotal * ($invoice_discount / 100), 2);
        }

        $computed_total = $display_subtotal - abs($discount_total) - $invoice_discount_amount;
        $amount_paid = $invoice->totalAmount - $invoice->balance;
    @endphp

    <table class="totals-wrapper">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-cell">
                <table class="totals">
                    <tr>
                        <td class="lbl">Subtotal</td>
                        <td class="val">${{ number_format($display_subtotal, 2) }}</td>
                    </tr>
                    @if($discount_lines->isNotEmpty())
                        <tr>
                            <td class="lbl">Discount ({{ $discount_percent }}%)</td>
                            <td class="val">-${{ number_format(abs($discount_total), 2) }}</td>
                        </tr>
                    @endif
                    @if($invoice_discount !== null && $invoice_discount > 0)
                        <tr>
                            <td class="lbl">Discount ({{ number_format($invoice_discount, 0) }}%)</td>
                            <td class="val">-${{ number_format($invoice_discount_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-main">
                        <td class="lbl">Total</td>
                        <td class="val">${{ number_format($computed_total, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Paid</td>
                        <td class="val">${{ number_format($amount_paid, 2) }}</td>
                    </tr>
                    <tr class="balance-due">
                        <td class="lbl">Balance Due</td>
                        <td class="val">${{ number_format($invoice->balanceDue, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table class="footer">
        <tr>
            <td>Universal Yums</td>
            <td class="r">Page {{ $current_page ?? 1 }} of {{ $total_pages ?? 1 }}</td>
        </tr>
    </table>

</body>
</html>
