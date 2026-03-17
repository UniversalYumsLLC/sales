<?php

namespace App\Services;

use App\Exceptions\InvoicePdfException;
use App\Models\CustomerSku;
use App\Models\EmailRecord;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ArAutomationService
{
    protected FulfilService $fulfil;

    protected ArEmailService $emailService;

    protected InvoicePdfService $pdfService;

    protected TestModeService $testMode;

    /**
     * Email timing constants (in days).
     */
    protected const DUE_REMINDER_DAYS_BEFORE = 7;

    protected const OVERDUE_NOTIFICATION_DAYS_AFTER = 1;

    protected const OVERDUE_FOLLOWUP_INTERVAL_DAYS = 7;

    public function __construct(
        FulfilService $fulfil,
        ArEmailService $emailService,
        InvoicePdfService $pdfService,
        TestModeService $testMode
    ) {
        $this->fulfil = $fulfil;
        $this->emailService = $emailService;
        $this->pdfService = $pdfService;
        $this->testMode = $testMode;
    }

    /**
     * Process all posted invoices with outstanding balance.
     *
     * This is the main entry point called by the scheduled job.
     *
     * @return array{processed: int, emails_sent: int, errors: int, skipped_edi: int}
     */
    public function processAllInvoices(): array
    {
        Log::info('AR Automation: Starting invoice processing', [
            'test_mode' => $this->testMode->isEnabled(),
        ]);

        $result = [
            'processed' => 0,
            'emails_sent' => 0,
            'errors' => 0,
            'skipped_edi' => 0,
        ];

        // Fetch posted invoices with balance > 0
        $invoices = $this->fetchPostedInvoicesWithBalance();

        Log::info('AR Automation: Found invoices to process', [
            'count' => count($invoices),
        ]);

        foreach ($invoices as $invoiceData) {
            try {
                // Sync invoice to local database
                $invoice = Invoice::syncFromFulfil($invoiceData);

                // Get customer AR settings
                $arSettings = $this->fulfil->getCustomerArSettings($invoice->fulfil_party_id);

                // Skip EDI customers
                if ($arSettings['edi']) {
                    $result['skipped_edi']++;
                    Log::debug('AR Automation: Skipping EDI customer', [
                        'invoice_id' => $invoice->fulfil_id,
                        'customer_id' => $invoice->fulfil_party_id,
                    ]);

                    continue;
                }

                // Log warning for consolidated invoicing (pending Fulfil support)
                if ($arSettings['consolidated_invoicing']) {
                    Log::warning('AR Automation: Customer has consolidated invoicing enabled, processing normally pending Fulfil support', [
                        'invoice_id' => $invoice->fulfil_id,
                        'customer_id' => $invoice->fulfil_party_id,
                        'consolidated_type' => $arSettings['consolidated_invoicing'],
                    ]);
                }

                // Process this invoice
                $emailsSent = $this->processInvoice($invoice, $invoiceData, $arSettings);

                $result['processed']++;
                $result['emails_sent'] += $emailsSent;
            } catch (\Exception $e) {
                $result['errors']++;
                Log::error('AR Automation: Error processing invoice', [
                    'invoice_id' => $invoiceData['id'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('AR Automation: Processing complete', $result);

        return $result;
    }

    /**
     * Fetch posted invoices with balance > 0 from Fulfil.
     */
    protected function fetchPostedInvoicesWithBalance(): array
    {
        // Get invoices with posted state, bust cache to get fresh data
        $allInvoices = $this->fulfil->getInvoices(['state' => Invoice::STATE_POSTED], bustCache: true);

        // Filter to only those with balance > 0
        return array_filter($allInvoices, function ($invoice) {
            return ($invoice['balance'] ?? 0) > 0;
        });
    }

    /**
     * Process a single invoice and determine which email(s) to send.
     *
     * @return int Number of emails sent
     */
    protected function processInvoice(Invoice $invoice, array $invoiceData, array $arSettings): int
    {
        $emailsSent = 0;

        // Get customer data for email
        $customerData = $this->getCustomerDataForEmail($invoice->fulfil_party_id, $invoiceData);

        // Check if customer uses AP portal
        $isApPortal = $this->customerUsesApPortal($customerData);

        // If customer requires SKUs, validate them before proceeding
        if ($arSettings['requires_customer_skus']) {
            $skuValidation = $this->validateCustomerSkus($invoice, $invoiceData);
            if (! $skuValidation['valid']) {
                // Send SKU mapping error notification and stop processing
                $this->emailService->sendSkuMappingError($customerData, $skuValidation['unmapped_skus']);
                Log::warning('AR Automation: SKU validation failed', [
                    'invoice_id' => $invoice->fulfil_id,
                    'unmapped_skus' => $skuValidation['unmapped_skus'],
                ]);

                return 1; // Count the SKU error email
            }
        }

        // Determine which automation to run based on invoice state
        $daysUntilDue = $invoice->daysUntilDue();
        $wasInitialSent = EmailRecord::wasInitialInvoiceSent($invoice->id);

        // Check if invoice was modified since last email
        $wasModified = $this->wasInvoiceModifiedSinceLastEmail($invoice, $invoiceData);

        // Route to appropriate automation
        if (! $wasInitialSent) {
            // Initial invoice email
            $emailsSent += $this->runInitialInvoiceAutomation($invoice, $invoiceData, $customerData, $isApPortal);
        } elseif ($wasModified) {
            // Invoice was modified - send update
            $emailsSent += $this->runModifiedInvoiceAutomation($invoice, $invoiceData, $customerData, $isApPortal);
        } elseif ($daysUntilDue !== null) {
            // Check timing-based automations
            if ($daysUntilDue > 0 && $daysUntilDue <= self::DUE_REMINDER_DAYS_BEFORE) {
                // Due reminder (7 days before)
                $emailsSent += $this->runDueReminderAutomation($invoice, $invoiceData, $customerData);
            } elseif ($daysUntilDue < 0) {
                // Overdue - check which notification to send
                $daysOverdue = abs($daysUntilDue);

                if ($daysOverdue >= self::OVERDUE_NOTIFICATION_DAYS_AFTER
                    && ! EmailRecord::wasEmailSent($invoice->id, EmailTemplate::TYPE_OVERDUE_NOTIFICATION)) {
                    // First overdue notification (1 day after due)
                    $emailsSent += $this->runOverdueNotificationAutomation($invoice, $invoiceData, $customerData);
                } elseif ($this->shouldSendOverdueFollowup($invoice, $daysOverdue)) {
                    // Weekly followup
                    $emailsSent += $this->runOverdueFollowupAutomation($invoice, $invoiceData, $customerData);
                }
            }
        }

        return $emailsSent;
    }

    /**
     * Run initial invoice automation.
     *
     * @return int 1 if email sent, 0 otherwise
     */
    protected function runInitialInvoiceAutomation(
        Invoice $invoice,
        array $invoiceData,
        array $customerData,
        bool $isApPortal
    ): int {
        Log::info('AR Automation: Sending initial invoice email', [
            'invoice_id' => $invoice->fulfil_id,
            'invoice_number' => $invoice->number,
            'customer_id' => $invoice->fulfil_party_id,
            'is_ap_portal' => $isApPortal,
        ]);

        try {
            // Generate PDF
            $pdfPath = $this->pdfService->getOrGenerate($invoice->fulfil_id);

            // Send email
            $sent = $this->emailService->sendInitialInvoice(
                $customerData,
                $this->buildInvoiceDataForEmail($invoice, $invoiceData),
                $pdfPath,
                $isApPortal
            );

            return $sent ? 1 : 0;
        } catch (InvoicePdfException $e) {
            // SKU validation failed during PDF generation
            Log::warning('AR Automation: PDF generation blocked', [
                'invoice_id' => $invoice->fulfil_id,
                'reason' => $e->getMessage(),
            ]);

            // Send SKU mapping error email
            $this->emailService->sendSkuMappingError($customerData, $e->getUnmappedSkus());

            return 1;
        }
    }

    /**
     * Run modified invoice automation.
     *
     * @return int 1 if email sent, 0 otherwise
     */
    protected function runModifiedInvoiceAutomation(
        Invoice $invoice,
        array $invoiceData,
        array $customerData,
        bool $isApPortal
    ): int {
        Log::info('AR Automation: Sending modified invoice email', [
            'invoice_id' => $invoice->fulfil_id,
            'invoice_number' => $invoice->number,
            'customer_id' => $invoice->fulfil_party_id,
            'is_ap_portal' => $isApPortal,
        ]);

        try {
            // Regenerate PDF with fresh data
            $pdfPath = $this->pdfService->regenerate($invoice->fulfil_id);

            // Send email
            $sent = $this->emailService->sendInvoiceModified(
                $customerData,
                $this->buildInvoiceDataForEmail($invoice, $invoiceData),
                $pdfPath,
                $isApPortal
            );

            return $sent ? 1 : 0;
        } catch (InvoicePdfException $e) {
            Log::warning('AR Automation: PDF generation blocked for modified invoice', [
                'invoice_id' => $invoice->fulfil_id,
                'reason' => $e->getMessage(),
            ]);

            $this->emailService->sendSkuMappingError($customerData, $e->getUnmappedSkus());

            return 1;
        }
    }

    /**
     * Run due reminder automation (7 days before due date).
     *
     * @return int 1 if email sent, 0 otherwise
     */
    protected function runDueReminderAutomation(
        Invoice $invoice,
        array $invoiceData,
        array $customerData
    ): int {
        // Check if already sent
        if (EmailRecord::wasEmailSent($invoice->id, EmailTemplate::TYPE_DUE_REMINDER)) {
            return 0;
        }

        Log::info('AR Automation: Sending due reminder', [
            'invoice_id' => $invoice->fulfil_id,
            'invoice_number' => $invoice->number,
            'days_until_due' => $invoice->daysUntilDue(),
        ]);

        // Get existing PDF or generate
        $pdfPath = $this->getExistingOrGeneratePdf($invoice);
        if (! $pdfPath) {
            return 0;
        }

        $sent = $this->emailService->sendDueReminder(
            $customerData,
            $this->buildInvoiceDataForEmail($invoice, $invoiceData),
            $pdfPath
        );

        return $sent ? 1 : 0;
    }

    /**
     * Run overdue notification automation (1 day after due date).
     *
     * @return int 1 if email sent, 0 otherwise
     */
    protected function runOverdueNotificationAutomation(
        Invoice $invoice,
        array $invoiceData,
        array $customerData
    ): int {
        Log::info('AR Automation: Sending overdue notification', [
            'invoice_id' => $invoice->fulfil_id,
            'invoice_number' => $invoice->number,
            'days_overdue' => abs($invoice->daysUntilDue()),
        ]);

        $pdfPath = $this->getExistingOrGeneratePdf($invoice);
        if (! $pdfPath) {
            return 0;
        }

        $sent = $this->emailService->sendOverdueNotification(
            $customerData,
            $this->buildInvoiceDataForEmail($invoice, $invoiceData),
            $pdfPath
        );

        return $sent ? 1 : 0;
    }

    /**
     * Run overdue followup automation (weekly after first overdue).
     *
     * @return int 1 if email sent, 0 otherwise
     */
    protected function runOverdueFollowupAutomation(
        Invoice $invoice,
        array $invoiceData,
        array $customerData
    ): int {
        Log::info('AR Automation: Sending overdue followup', [
            'invoice_id' => $invoice->fulfil_id,
            'invoice_number' => $invoice->number,
            'days_overdue' => abs($invoice->daysUntilDue()),
        ]);

        $pdfPath = $this->getExistingOrGeneratePdf($invoice);
        if (! $pdfPath) {
            return 0;
        }

        $sent = $this->emailService->sendOverdueFollowup(
            $customerData,
            $this->buildInvoiceDataForEmail($invoice, $invoiceData),
            $pdfPath
        );

        return $sent ? 1 : 0;
    }

    /**
     * Get customer data formatted for email service.
     */
    protected function getCustomerDataForEmail(int $fulfilPartyId, array $invoiceData): array
    {
        // Fetch customer details from Fulfil
        $customer = $this->fulfil->getContact($fulfilPartyId);

        return [
            'id' => $fulfilPartyId,
            'name' => $customer['name'] ?? '',
            'ap_contacts' => $customer['accounts_payable'] ?? [],
            'ap_portal_url' => $this->extractApPortalUrl($customer['accounts_payable'] ?? []),
        ];
    }

    /**
     * Check if customer uses AP portal for invoice submission.
     */
    protected function customerUsesApPortal(array $customerData): bool
    {
        return ! empty($customerData['ap_portal_url']);
    }

    /**
     * Extract AP portal URL from accounts_payable contacts.
     */
    protected function extractApPortalUrl(array $apContacts): ?string
    {
        foreach ($apContacts as $contact) {
            $name = $contact['name'] ?? '';
            $value = $contact['value'] ?? '';

            if ($name === 'AP Portal' && str_starts_with($value, 'http')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Validate customer SKU mappings for an invoice.
     *
     * @return array{valid: bool, unmapped_skus: array}
     */
    protected function validateCustomerSkus(Invoice $invoice, array $invoiceData): array
    {
        // Get detailed invoice data with line items
        $detailedInvoice = $this->fulfil->getInvoiceForPdf($invoice->fulfil_id);

        // Extract product codes from line items
        $productCodes = [];
        foreach ($detailedInvoice['lines'] ?? [] as $line) {
            if (! empty($line['product_code'])) {
                $productCodes[] = $line['product_code'];
            }
        }

        if (empty($productCodes)) {
            return ['valid' => true, 'unmapped_skus' => []];
        }

        // Check for unmapped SKUs
        $unmappedSkus = CustomerSku::getUnmappedSkus($invoice->fulfil_party_id, $productCodes);

        return [
            'valid' => $unmappedSkus->isEmpty(),
            'unmapped_skus' => $unmappedSkus->toArray(),
        ];
    }

    /**
     * Check if invoice was modified since last email was sent.
     */
    protected function wasInvoiceModifiedSinceLastEmail(Invoice $invoice, array $invoiceData): bool
    {
        // Get the most recent email record for this invoice
        $lastEmail = EmailRecord::where('invoice_id', $invoice->id)
            ->latest('sent_at')
            ->first();

        if (! $lastEmail) {
            return false;
        }

        // Compare Fulfil's write_date with our last email time
        $fulfilWriteDate = isset($invoiceData['write_date'])
            ? Carbon::parse($invoiceData['write_date'])
            : null;

        if (! $fulfilWriteDate) {
            return false;
        }

        return $fulfilWriteDate->isAfter($lastEmail->sent_at);
    }

    /**
     * Check if we should send an overdue followup email.
     */
    protected function shouldSendOverdueFollowup(Invoice $invoice, int $daysOverdue): bool
    {
        // Must be past the first notification period
        if ($daysOverdue < self::OVERDUE_NOTIFICATION_DAYS_AFTER) {
            return false;
        }

        // Get the last followup email
        $lastFollowup = EmailRecord::getLastEmailOfType($invoice->id, EmailTemplate::TYPE_OVERDUE_FOLLOWUP);

        if (! $lastFollowup) {
            // No followup sent yet - check if it's been at least a week since first overdue
            $firstOverdue = EmailRecord::getLastEmailOfType($invoice->id, EmailTemplate::TYPE_OVERDUE_NOTIFICATION);
            if (! $firstOverdue) {
                return false; // First overdue notification must be sent first
            }

            return $firstOverdue->sent_at->diffInDays(now()) >= self::OVERDUE_FOLLOWUP_INTERVAL_DAYS;
        }

        // Check if it's been at least a week since last followup
        return $lastFollowup->sent_at->diffInDays(now()) >= self::OVERDUE_FOLLOWUP_INTERVAL_DAYS;
    }

    /**
     * Get existing PDF path or generate a new one.
     */
    protected function getExistingOrGeneratePdf(Invoice $invoice): ?string
    {
        try {
            // Try to get cached PDF first
            if ($this->pdfService->hasCachedPdf($invoice->number)) {
                return "invoices/{$invoice->number}.pdf";
            }

            // Generate new PDF
            return $this->pdfService->getOrGenerate($invoice->fulfil_id);
        } catch (InvoicePdfException $e) {
            Log::warning('AR Automation: Could not generate PDF', [
                'invoice_id' => $invoice->fulfil_id,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build invoice data array for email service.
     */
    protected function buildInvoiceDataForEmail(Invoice $invoice, array $invoiceData): array
    {
        return [
            'id' => $invoice->fulfil_id,
            'number' => $invoice->number,
            'invoice_date' => $invoiceData['invoice_date'] ?? $invoice->created_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'total_amount' => $invoice->total_amount,
            'balance_due' => $invoice->balance,
        ];
    }

    /**
     * Manually resend an email for a specific invoice (for testing).
     *
     * @param  int  $fulfilInvoiceId  The Fulfil invoice ID
     * @param  string  $emailType  The type of email to send
     * @return bool Success status
     */
    public function resendEmail(int $fulfilInvoiceId, string $emailType): bool
    {
        // Fetch invoice data from Fulfil
        $invoiceData = null;
        $allInvoices = $this->fulfil->getInvoices([], bustCache: true);
        foreach ($allInvoices as $inv) {
            if ($inv['id'] == $fulfilInvoiceId) {
                $invoiceData = $inv;
                break;
            }
        }

        if (! $invoiceData) {
            Log::error('AR Automation: Could not find invoice in Fulfil for resend', [
                'fulfil_invoice_id' => $fulfilInvoiceId,
            ]);

            return false;
        }

        // Sync invoice to local database
        $invoice = Invoice::syncFromFulfil($invoiceData);

        // Get AR settings and customer data
        $arSettings = $this->fulfil->getCustomerArSettings($invoice->fulfil_party_id);
        $customerData = $this->getCustomerDataForEmail($invoice->fulfil_party_id, $invoiceData);
        $isApPortal = $this->customerUsesApPortal($customerData);

        Log::info('AR Automation: Manual resend requested', [
            'invoice_id' => $invoiceId,
            'email_type' => $emailType,
            'customer_id' => $invoice->fulfil_party_id,
        ]);

        // Route to appropriate automation
        switch ($emailType) {
            case EmailTemplate::TYPE_INITIAL_INVOICE:
            case EmailTemplate::TYPE_INITIAL_INVOICE_AP_PORTAL:
                return $this->runInitialInvoiceAutomation($invoice, $invoiceData, $customerData, $isApPortal) > 0;

            case EmailTemplate::TYPE_INVOICE_MODIFIED:
            case EmailTemplate::TYPE_INVOICE_MODIFIED_AP_PORTAL:
                return $this->runModifiedInvoiceAutomation($invoice, $invoiceData, $customerData, $isApPortal) > 0;

            case EmailTemplate::TYPE_DUE_REMINDER:
                $pdfPath = $this->getExistingOrGeneratePdf($invoice);

                return $pdfPath && $this->emailService->sendDueReminder(
                    $customerData,
                    $this->buildInvoiceDataForEmail($invoice, $invoiceData),
                    $pdfPath
                );

            case EmailTemplate::TYPE_OVERDUE_NOTIFICATION:
                $pdfPath = $this->getExistingOrGeneratePdf($invoice);

                return $pdfPath && $this->emailService->sendOverdueNotification(
                    $customerData,
                    $this->buildInvoiceDataForEmail($invoice, $invoiceData),
                    $pdfPath
                );

            case EmailTemplate::TYPE_OVERDUE_FOLLOWUP:
                $pdfPath = $this->getExistingOrGeneratePdf($invoice);

                return $pdfPath && $this->emailService->sendOverdueFollowup(
                    $customerData,
                    $this->buildInvoiceDataForEmail($invoice, $invoiceData),
                    $pdfPath
                );

            default:
                Log::error('AR Automation: Unknown email type for resend', [
                    'email_type' => $emailType,
                ]);

                return false;
        }
    }
}
