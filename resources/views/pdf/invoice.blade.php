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
                @if(file_exists(public_path('images/universal-yums-logo.png')) && filesize(public_path('images/universal-yums-logo.png')) > 100)
                    <img src="{{ public_path('images/universal-yums-logo.png') }}" alt="Universal Yums" class="logo">
                @else
                    <div style="font-size: 18pt; font-weight: bold; color: #333; margin-bottom: 14px;">UNIVERSAL YUMS</div>
                @endif
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
