# AR Automation - Session 5: Automation & Polling

## Overview

This session implements the core automation engine for AR Automation. It includes the daily polling job that checks invoices and triggers appropriate email workflows based on invoice state, dates, and customer configuration.

## Prerequisites

**All previous sessions must be completed:**

- **Session 1: Data Foundation** - Database tables, customer fields, invoice fields
- **Session 2: Customer & UI** - Customer SKU mappings, AP contact validation
- **Session 3: PDF Generation** - InvoicePdfService, DTOs, Blade template
- **Session 4: Email Infrastructure** - ArEmailService, templates, Test Mode

## Scope

1. Daily polling job (scheduled task)
2. Invoice processing logic
3. Email automation flows (5 types)
4. Consolidated invoicing (skeleton)
5. Error handling and logging

---

## 1. Daily Polling Job

### 1.1 Schedule

- **Frequency**: Daily at 2:00 AM EST
- **Laravel Scheduler**: Add to `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new ProcessArInvoicesJob())
        ->dailyAt('02:00')
        ->timezone('America/New_York');
}
```

### 1.2 Job: ProcessArInvoicesJob

```php
class ProcessArInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ArAutomationService $service)
    {
        $service->processAllInvoices();
    }
}
```

---

## 2. Invoice Fetching

### 2.1 Query Criteria

Fetch all invoices from Fulfil that match:

- **Journal**: Accounts Receivable - B2B
- **State**: Posted

### 2.2 Fields to Fetch

For each invoice, retrieve:

| Field | Fulfil Field | Purpose |
|-------|--------------|---------|
| Invoice Number | number | Unique identifier |
| Due Date | payment_term_date or earliest maturity date | For reminder calculations |
| Create Date | create_date | Store locally on first fetch |
| Write Date | write_date | Detect modifications |
| Customer ID | party | Link to customer record |

### 2.3 Fulfil API Call

```php
// Pseudo-code for Fulfil query
$invoices = $fulfil->search('account.invoice', [
    ['journal.name', '=', 'Accounts Receivable - B2B'],
    ['state', '=', 'posted'],
], [
    'number',
    'payment_term_date',
    'create_date',
    'write_date',
    'party',
    'total_amount',
    'balance_due',
]);
```

---

## 3. AR Automation Service

### 3.1 Service Class

```php
class ArAutomationService
{
    public function __construct(
        protected FulfilService $fulfil,
        protected ArEmailService $emailService,
        protected InvoicePdfService $pdfService,
        protected TestModeService $testMode
    ) {}

    /**
     * Main entry point - process all invoices
     */
    public function processAllInvoices(): void
    {
        $invoices = $this->fetchPostedInvoices();

        foreach ($invoices as $invoiceData) {
            $this->processInvoice($invoiceData);
        }
    }

    /**
     * Process a single invoice through all automation rules
     */
    protected function processInvoice(array $invoiceData): void
    {
        $customer = $this->getCustomer($invoiceData['party']);

        // Skip EDI customers
        if ($customer->edi) {
            return;
        }

        $invoice = $this->syncInvoiceLocally($invoiceData);

        // Determine which automation to run
        $this->runInitialInvoiceAutomation($invoice, $customer);
        $this->runModifiedInvoiceAutomation($invoice, $customer);
        $this->runDueReminderAutomation($invoice, $customer);
        $this->runOverdueNotificationAutomation($invoice, $customer);
        $this->runOverdueFollowupAutomation($invoice, $customer);
    }
}
```

---

## 4. Email Automation Flows

### 4.1 Initial Invoice

Sends the first invoice email to a customer.

#### Conditions

1. Initial Invoice email NOT already sent for this Invoice Number
2. Customer does NOT use EDI
3. Route based on AP Portal setting:
   - **Option A (Email)**: Customer does NOT use AP Portal
   - **Option B (AP Portal)**: Customer uses AP Portal

#### Flow

```
1. Check conditions
       ↓
2. Get full invoice data from Fulfil
       ↓
3. Check consolidated invoicing
   ├─ No  → Continue
   └─ Yes → Run consolidation process, then continue
       ↓
4. Check customer SKU requirement
   ├─ No  → Continue
   └─ Yes → Check all SKUs mapped
            ├─ Yes → Continue
            └─ No  → Send SKU Mapping Error email, STOP
       ↓
5. Generate invoice PDF
       ↓
6. Send email (template based on AP Portal setting)
   ├─ No Portal  → Send to customer AP contacts
   └─ Has Portal → Send to accountsreceivable@universalyums.com
       ↓
7. Record email in database
   - Timestamp
   - PDF path
```

#### Implementation

```php
protected function runInitialInvoiceAutomation(Invoice $invoice, Customer $customer): void
{
    // Check if already sent
    if ($this->emailAlreadySent($invoice, ['initial_invoice', 'initial_invoice_ap_portal'])) {
        return;
    }

    // Get full invoice data
    $invoiceData = $this->fulfil->getInvoiceDetails($invoice->fulfil_id);

    // Handle consolidated invoicing
    if ($customer->consolidated_invoicing === 'Consolidate invoices shipped same day') {
        $invoiceData = $this->handleConsolidation($invoice, $customer);
        if ($invoiceData === null) {
            return; // Consolidation in progress or blocked
        }
    }

    // Check SKU mapping
    if ($customer->invoice_requires_customer_skus) {
        $unmappedSkus = $this->getUnmappedSkus($invoiceData, $customer);
        if ($unmappedSkus->isNotEmpty()) {
            $this->sendSkuMappingError($customer, $invoice, $unmappedSkus);
            return;
        }
    }

    // Generate PDF
    $pdfPath = $this->pdfService->generate($invoice->id);

    // Send email
    $templateKey = $customer->uses_ap_portal
        ? 'initial_invoice_ap_portal'
        : 'initial_invoice';

    $this->emailService->send($templateKey, $customer, $invoice, $pdfPath);
}
```

---

### 4.2 Invoice Modified

Sends notification when an invoice has been modified in Fulfil.

#### Conditions

1. Initial Invoice email WAS already sent for this Invoice Number
2. `write_date` in Fulfil DIFFERS from locally stored `last_modified_date`
3. Customer does NOT use EDI
4. Route based on AP Portal setting (same as Initial Invoice)

#### Flow

Same as Initial Invoice, but uses different email templates:
- `invoice_modified` (no portal)
- `invoice_modified_ap_portal` (has portal)

#### Implementation

```php
protected function runModifiedInvoiceAutomation(Invoice $invoice, Customer $customer): void
{
    // Must have sent initial invoice first
    if (!$this->emailAlreadySent($invoice, ['initial_invoice', 'initial_invoice_ap_portal'])) {
        return;
    }

    // Check if modified since last check
    $currentWriteDate = $this->fulfil->getInvoiceWriteDate($invoice->fulfil_id);
    if ($currentWriteDate == $invoice->last_modified_date) {
        return; // No changes
    }

    // Update local record
    $invoice->update(['last_modified_date' => $currentWriteDate]);

    // Same flow as initial: consolidation check, SKU check, PDF, email
    // ... (similar to initial invoice logic)

    $templateKey = $customer->uses_ap_portal
        ? 'invoice_modified_ap_portal'
        : 'invoice_modified';

    $this->emailService->send($templateKey, $customer, $invoice, $pdfPath);
}
```

---

### 4.3 Upcoming Invoice Due Reminder (Due - 7 Days)

Sends reminder 7 days before invoice due date.

#### Conditions

1. Due Reminder email NOT already sent for this Invoice Number
2. Today is 7 days before due date (or invoice is already within 7 days but reminder not sent)

#### Flow

```
1. Check: Due Reminder not already sent
       ↓
2. Check: Due date is 7 days away (or less, but > due date)
       ↓
3. Get existing PDF (from initial invoice)
       ↓
4. Send email to customer AP contacts
       ↓
5. Record email
```

#### Implementation

```php
protected function runDueReminderAutomation(Invoice $invoice, Customer $customer): void
{
    if ($this->emailAlreadySent($invoice, 'due_reminder')) {
        return;
    }

    $daysUntilDue = now()->diffInDays($invoice->due_date, false);

    // Send if within 7 days but not yet due
    if ($daysUntilDue <= 7 && $daysUntilDue > 0) {
        $pdfPath = $this->getExistingPdf($invoice);
        $this->emailService->send('due_reminder', $customer, $invoice, $pdfPath);
    }
}
```

---

### 4.4 Upcoming Invoice Overdue Notification (Due + 1 Day)

Sends notification 1 day after invoice is overdue.

#### Conditions

1. Overdue Notification email NOT already sent for this Invoice Number
2. Today is at least 1 day past due date

#### Flow

```
1. Check: Overdue Notification not already sent
       ↓
2. Check: Due date is at least 1 day in the past
       ↓
3. Get existing PDF
       ↓
4. Send email to customer AP contacts
       ↓
5. Record email
```

#### Implementation

```php
protected function runOverdueNotificationAutomation(Invoice $invoice, Customer $customer): void
{
    if ($this->emailAlreadySent($invoice, 'overdue_notification')) {
        return;
    }

    $daysPastDue = now()->diffInDays($invoice->due_date, false);

    // Send if at least 1 day overdue
    if ($daysPastDue <= -1) {
        $pdfPath = $this->getExistingPdf($invoice);
        $this->emailService->send('overdue_notification', $customer, $invoice, $pdfPath);
    }
}
```

---

### 4.5 Upcoming Invoice Overdue Follow-Up (Weekly)

Sends weekly follow-up for overdue invoices.

#### Conditions

1. Overdue Follow-Up email NOT sent in the last 7 days for this Invoice Number
2. Invoice is overdue

#### Flow

```
1. Check: Invoice is overdue
       ↓
2. Check: Last follow-up was more than 7 days ago (or never sent)
       ↓
3. Get existing PDF
       ↓
4. Send email to customer AP contacts
       ↓
5. Record email
```

#### Implementation

```php
protected function runOverdueFollowupAutomation(Invoice $invoice, Customer $customer): void
{
    // Must be overdue
    if ($invoice->due_date >= now()) {
        return;
    }

    // Check last follow-up
    $lastFollowup = EmailRecord::where('invoice_id', $invoice->id)
        ->where('email_type', 'overdue_followup')
        ->latest('sent_at')
        ->first();

    // Send if never sent OR last sent more than 7 days ago
    if (!$lastFollowup || $lastFollowup->sent_at->diffInDays(now()) >= 7) {
        $pdfPath = $this->getExistingPdf($invoice);
        $this->emailService->send('overdue_followup', $customer, $invoice, $pdfPath);
    }
}
```

---

## 5. Consolidated Invoice Process

**Note**: This feature is partially blocked pending Fulfil support response about un-posting invoices.

### 5.1 Purpose

Merge multiple invoices for the same customer shipped on the same day into a single invoice.

### 5.2 Trigger

Applies to customers with `consolidated_invoicing` = "Consolidate invoices shipped same day"

### 5.3 Logic (Skeleton)

```php
protected function handleConsolidation(Invoice $invoice, Customer $customer): ?array
{
    // Get all new invoices for this customer from today's batch
    $newInvoices = $this->getNewInvoicesForCustomer($customer);

    // If only 1 invoice, no consolidation needed
    if ($newInvoices->count() === 1) {
        return $this->fulfil->getInvoiceDetails($invoice->fulfil_id);
    }

    // Multiple invoices - need to consolidate
    if ($newInvoices->count() >= 2) {
        // BLOCKED: Un-post invoices in Fulfil
        // TODO: Implement when Fulfil provides un-post API method
        // $this->fulfil->unpostInvoices($newInvoices->pluck('fulfil_id'));

        // BLOCKED: Merge invoices in Fulfil
        // TODO: Implement when un-post is available
        // $mergedInvoice = $this->fulfil->mergeInvoices($newInvoices->pluck('fulfil_id'));

        // For now, log and skip consolidation
        Log::warning('Invoice consolidation pending Fulfil API support', [
            'customer_id' => $customer->id,
            'invoice_count' => $newInvoices->count(),
        ]);

        // Return null to indicate consolidation is blocked
        // The calling code should handle this appropriately
        return null;
    }

    return null;
}
```

### 5.4 Future Implementation Notes

When Fulfil support responds with the un-post method:

1. Call Fulfil API to un-post all invoices in the group
2. Call Fulfil API to merge invoices
3. Return the merged invoice data
4. Update local records to reflect the merge

---

## 6. SKU Mapping Validation

### 6.1 Check for Unmapped SKUs

```php
protected function getUnmappedSkus(array $invoiceData, Customer $customer): Collection
{
    $invoiceSkus = collect($invoiceData['lines'])
        ->pluck('product_code')
        ->unique();

    $mappedSkus = CustomerSku::where('customer_id', $customer->id)
        ->pluck('yums_sku');

    return $invoiceSkus->diff($mappedSkus);
}
```

### 6.2 Send SKU Mapping Error

```php
protected function sendSkuMappingError(
    Customer $customer,
    Invoice $invoice,
    Collection $unmappedSkus
): void {
    // The email service will handle the template and formatting
    $this->emailService->send(
        'sku_mapping_error',
        $customer,
        $invoice,
        null,
        ['missing_skus' => $unmappedSkus->implode(', ')]
    );
}
```

---

## 7. Helper Methods

### 7.1 Check if Email Already Sent

```php
protected function emailAlreadySent(Invoice $invoice, string|array $types): bool
{
    $types = (array) $types;

    return EmailRecord::where('invoice_id', $invoice->id)
        ->whereIn('email_type', $types)
        ->exists();
}
```

### 7.2 Get Existing PDF

```php
protected function getExistingPdf(Invoice $invoice): ?string
{
    // Find the most recent email record with a PDF
    $record = EmailRecord::where('invoice_id', $invoice->id)
        ->whereNotNull('pdf_path')
        ->latest('sent_at')
        ->first();

    return $record?->pdf_path;
}
```

### 7.3 Sync Invoice Locally

```php
protected function syncInvoiceLocally(array $invoiceData): Invoice
{
    return Invoice::updateOrCreate(
        ['fulfil_id' => $invoiceData['id']],
        [
            'number' => $invoiceData['number'],
            'due_date' => $invoiceData['payment_term_date'],
            'created_date' => $invoiceData['create_date'],
            'last_modified_date' => $invoiceData['write_date'],
            'customer_id' => $this->mapFulfilPartyToCustomer($invoiceData['party']),
        ]
    );
}
```

---

## 8. Error Handling

### 8.1 Logging

Log all automation actions for debugging and audit:

```php
Log::info('AR Automation: Processing invoice', [
    'invoice_number' => $invoice->number,
    'customer_id' => $customer->id,
    'action' => 'initial_invoice',
]);

Log::error('AR Automation: Failed to generate PDF', [
    'invoice_id' => $invoice->id,
    'error' => $exception->getMessage(),
]);
```

### 8.2 Exception Handling

Wrap processing in try-catch to prevent one invoice failure from stopping the entire job:

```php
protected function processInvoice(array $invoiceData): void
{
    try {
        // ... processing logic
    } catch (\Exception $e) {
        Log::error('AR Automation: Invoice processing failed', [
            'invoice_data' => $invoiceData,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Optionally notify admins of failures
        // $this->notifyAdminOfFailure($invoiceData, $e);
    }
}
```

### 8.3 Retry Logic

Consider implementing retry logic for transient failures (Fulfil API timeouts, etc.):

```php
class ProcessArInvoicesJob implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [60, 300, 600]; // 1min, 5min, 10min
}
```

---

## 9. Automation Decision Tree

```
For each Posted Invoice in AR-B2B:
│
├─ Customer uses EDI? → Yes → SKIP
│
└─ No
   │
   ├─ Initial Invoice sent? → No → RUN Initial Invoice Flow
   │                              │
   │                              ├─ Consolidation needed?
   │                              │   └─ Yes → Consolidate (if possible)
   │                              │
   │                              ├─ SKU mapping required?
   │                              │   └─ Yes → Check mappings
   │                              │             └─ Missing? → Send error, STOP
   │                              │
   │                              └─ Generate PDF → Send Email → Record
   │
   └─ Yes
      │
      ├─ Invoice modified? → Yes → RUN Modified Invoice Flow
      │                            (same sub-flow as Initial)
      │
      ├─ Due in ≤7 days? → Yes → Due Reminder sent? → No → Send Reminder
      │
      ├─ Overdue ≥1 day? → Yes → Overdue Notice sent? → No → Send Notice
      │
      └─ Overdue? → Yes → Follow-up sent in last 7 days? → No → Send Follow-up
```

---

## 10. Testing

### 10.1 Test Scenarios

Create tests for:

1. **Initial Invoice - Email path**
   - Non-EDI customer without AP Portal
   - Verify correct template used
   - Verify PDF attached
   - Verify email recorded

2. **Initial Invoice - AP Portal path**
   - Non-EDI customer with AP Portal
   - Verify email sent to internal address

3. **SKU Mapping Error**
   - Customer requires SKUs
   - Invoice has unmapped SKU
   - Verify error email sent

4. **Modified Invoice Detection**
   - Change write_date in mock
   - Verify modification email triggered

5. **Due Reminder Timing**
   - Invoice due in 7 days → should send
   - Invoice due in 8 days → should not send
   - Invoice due in 3 days → should send (already in window)

6. **Overdue Notification**
   - Invoice 1 day overdue → should send
   - Invoice on due date → should not send

7. **Weekly Follow-up**
   - Last follow-up 8 days ago → should send
   - Last follow-up 3 days ago → should not send

8. **EDI Customer Skip**
   - EDI customer → no emails sent

9. **Test Mode**
   - External email addresses blocked
   - Fulfil sandbox used

### 10.2 Manual Testing with Test Mode

Enable Test Mode to safely test in production:

1. Enable Test Mode in admin settings
2. Create test customer with @universalyums.com AP contact
3. Create test invoice in Fulfil Sandbox
4. Manually trigger job or wait for scheduled run
5. Verify email received at test address

---

## Deliverables Checklist

- [ ] Job: Create ProcessArInvoicesJob
- [ ] Scheduler: Add daily 2 AM EST schedule
- [ ] Service: Create ArAutomationService
- [ ] Method: Implement fetchPostedInvoices from Fulfil
- [ ] Method: Implement processInvoice orchestration
- [ ] Method: Implement runInitialInvoiceAutomation
- [ ] Method: Implement runModifiedInvoiceAutomation
- [ ] Method: Implement runDueReminderAutomation
- [ ] Method: Implement runOverdueNotificationAutomation
- [ ] Method: Implement runOverdueFollowupAutomation
- [ ] Method: Implement handleConsolidation (skeleton)
- [ ] Method: Implement getUnmappedSkus
- [ ] Method: Implement sendSkuMappingError
- [ ] Helper: emailAlreadySent
- [ ] Helper: getExistingPdf
- [ ] Helper: syncInvoiceLocally
- [ ] Logging: Add comprehensive logging
- [ ] Error handling: Try-catch with logging
- [ ] Tests: Unit tests for each automation flow
- [ ] Tests: Integration test for full job run

---

## Dependencies

- Session 1: Data Foundation (all database structures)
- Session 2: Customer & UI (customer SKU mappings)
- Session 3: PDF Generation (InvoicePdfService)
- Session 4: Email Infrastructure (ArEmailService, Test Mode)

## Known Limitations

- **Consolidated Invoicing**: Blocked pending Fulfil API clarification for un-posting invoices. Skeleton implementation in place.
