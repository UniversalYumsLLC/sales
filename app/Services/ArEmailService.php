<?php

namespace App\Services;

use App\Mail\ArInvoiceEmail;
use App\Models\EmailRecord;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ArEmailService
{
    /**
     * The internal AR email address.
     */
    protected const AR_EMAIL = 'accountsreceivable@universalyums.com';

    protected TestModeService $testMode;

    protected InvoicePdfService $pdfService;

    public function __construct(TestModeService $testMode, InvoicePdfService $pdfService)
    {
        $this->testMode = $testMode;
        $this->pdfService = $pdfService;
    }

    /**
     * Send an AR email.
     *
     * @param  string  $templateKey  Template to use
     * @param  array  $customerData  Customer data with keys: id, name, ap_contacts, ap_portal_url
     * @param  array|null  $invoiceData  Invoice data with keys: id, number, invoice_date, due_date, total_amount, balance_due
     * @param  string|null  $pdfPath  Path to PDF attachment
     * @param  array  $extraData  Additional placeholder data (e.g., missing_skus)
     * @return bool Success status
     */
    public function send(
        string $templateKey,
        array $customerData,
        ?array $invoiceData = null,
        ?string $pdfPath = null,
        array $extraData = []
    ): bool {
        // Load the template
        $template = EmailTemplate::getByKey($templateKey);
        if (! $template) {
            Log::error('AR Email: Template not found', ['template_key' => $templateKey]);

            return false;
        }

        // Get recipients
        $recipients = $this->getRecipients($templateKey, $customerData);
        if (empty($recipients)) {
            Log::warning('AR Email: No valid recipients', [
                'template_key' => $templateKey,
                'customer_id' => $customerData['id'] ?? null,
                'test_mode' => $this->testMode->isEnabled(),
            ]);

            return false;
        }

        // Build placeholder data
        $placeholderData = $this->buildPlaceholderData($customerData, $invoiceData, $extraData);

        // Render the template
        $rendered = $template->render($placeholderData);

        // Get the full PDF path if provided
        $fullPdfPath = null;
        if ($pdfPath) {
            $fullPdfPath = $this->pdfService->getFullPath($pdfPath);
        }

        // Send the email
        try {
            $mailable = new ArInvoiceEmail(
                $rendered['subject'],
                $rendered['body'],
                $fullPdfPath
            );

            Mail::to($recipients)
                ->send($mailable->from(self::AR_EMAIL, 'Universal Yums Accounts Receivable'));

            // Record the email
            $this->recordEmail(
                $templateKey,
                $customerData['id'] ?? null,
                $invoiceData['id'] ?? null,
                $pdfPath
            );

            Log::info('AR Email sent successfully', [
                'template_key' => $templateKey,
                'customer_id' => $customerData['id'] ?? null,
                'invoice_id' => $invoiceData['id'] ?? null,
                'recipients' => $recipients,
                'test_mode' => $this->testMode->isEnabled(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('AR Email: Failed to send', [
                'template_key' => $templateKey,
                'customer_id' => $customerData['id'] ?? null,
                'invoice_id' => $invoiceData['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send SKU mapping error notification.
     *
     * @param  array  $customerData  Customer data
     * @param  array  $unmappedSkus  List of unmapped SKUs
     */
    public function sendSkuMappingError(array $customerData, array $unmappedSkus): bool
    {
        $formattedSkus = '<ul>'.implode('', array_map(fn ($sku) => '<li>'.e($sku).'</li>', $unmappedSkus)).'</ul>';

        return $this->send(
            EmailTemplate::TYPE_SKU_MAPPING_ERROR,
            $customerData,
            null,
            null,
            ['missing_skus' => $formattedSkus]
        );
    }

    /**
     * Send initial invoice email.
     *
     * @param  array  $customerData  Customer data with ap_contacts and ap_portal_url
     * @param  array  $invoiceData  Invoice data
     * @param  string  $pdfPath  Path to PDF
     * @param  bool  $isApPortal  Whether customer uses AP portal
     */
    public function sendInitialInvoice(
        array $customerData,
        array $invoiceData,
        string $pdfPath,
        bool $isApPortal = false
    ): bool {
        $templateKey = $isApPortal
            ? EmailTemplate::TYPE_INITIAL_INVOICE_AP_PORTAL
            : EmailTemplate::TYPE_INITIAL_INVOICE;

        return $this->send($templateKey, $customerData, $invoiceData, $pdfPath);
    }

    /**
     * Send invoice modified email.
     */
    public function sendInvoiceModified(
        array $customerData,
        array $invoiceData,
        string $pdfPath,
        bool $isApPortal = false
    ): bool {
        $templateKey = $isApPortal
            ? EmailTemplate::TYPE_INVOICE_MODIFIED_AP_PORTAL
            : EmailTemplate::TYPE_INVOICE_MODIFIED;

        return $this->send($templateKey, $customerData, $invoiceData, $pdfPath);
    }

    /**
     * Send due reminder email (7 days before due date).
     */
    public function sendDueReminder(array $customerData, array $invoiceData, string $pdfPath): bool
    {
        return $this->send(EmailTemplate::TYPE_DUE_REMINDER, $customerData, $invoiceData, $pdfPath);
    }

    /**
     * Send overdue notification (1 day after due date).
     */
    public function sendOverdueNotification(array $customerData, array $invoiceData, string $pdfPath): bool
    {
        return $this->send(EmailTemplate::TYPE_OVERDUE_NOTIFICATION, $customerData, $invoiceData, $pdfPath);
    }

    /**
     * Send overdue follow-up (weekly).
     */
    public function sendOverdueFollowup(array $customerData, array $invoiceData, string $pdfPath): bool
    {
        return $this->send(EmailTemplate::TYPE_OVERDUE_FOLLOWUP, $customerData, $invoiceData, $pdfPath);
    }

    /**
     * Get recipients for a template type.
     */
    protected function getRecipients(string $templateKey, array $customerData): array
    {
        // Internal templates always go to AR email
        $internalTemplates = [
            EmailTemplate::TYPE_SKU_MAPPING_ERROR,
            EmailTemplate::TYPE_INITIAL_INVOICE_AP_PORTAL,
            EmailTemplate::TYPE_INVOICE_MODIFIED_AP_PORTAL,
        ];

        if (in_array($templateKey, $internalTemplates)) {
            $recipients = [self::AR_EMAIL];
        } else {
            // Customer templates go to AP contacts
            $recipients = $this->extractApContactEmails($customerData);
        }

        // Filter recipients based on test mode
        return $this->testMode->filterEmails($recipients);
    }

    /**
     * Extract AP contact emails from customer data.
     */
    protected function extractApContactEmails(array $customerData): array
    {
        $apContacts = $customerData['ap_contacts'] ?? [];
        $emails = [];

        foreach ($apContacts as $contact) {
            $value = $contact['value'] ?? $contact['email'] ?? '';
            // Only include if it looks like an email (not a URL)
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $value;
            }
        }

        return array_unique($emails);
    }

    /**
     * Build placeholder data for template rendering.
     */
    protected function buildPlaceholderData(array $customerData, ?array $invoiceData, array $extraData): array
    {
        $data = [
            'customer_name' => $customerData['name'] ?? '',
        ];

        if ($invoiceData) {
            $data['invoice_number'] = $invoiceData['number'] ?? '';
            $data['invoice_date'] = $this->formatDate($invoiceData['invoice_date'] ?? null);
            $data['due_date'] = $this->formatDate($invoiceData['due_date'] ?? null);
            $data['total_amount'] = $this->formatCurrency($invoiceData['total_amount'] ?? 0);
            $data['balance_due'] = $this->formatCurrency($invoiceData['balance_due'] ?? $invoiceData['balance'] ?? 0);
        }

        // AP Portal URL for portal templates
        if (isset($customerData['ap_portal_url'])) {
            $data['ap_portal_url'] = $customerData['ap_portal_url'];
        }

        // Merge any extra data (like missing_skus)
        return array_merge($data, $extraData);
    }

    /**
     * Format a date for display in emails.
     */
    protected function formatDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('F j, Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format currency for display in emails.
     */
    protected function formatCurrency(mixed $amount): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0;

        return '$'.number_format($value, 2);
    }

    /**
     * Record the sent email.
     *
     * @param  int|null  $fulfilInvoiceId  The Fulfil invoice ID (not local DB ID)
     */
    protected function recordEmail(
        string $templateKey,
        ?int $customerId,
        ?int $fulfilInvoiceId,
        ?string $pdfPath
    ): void {
        if (! $customerId) {
            return;
        }

        // Look up the local invoice ID from the Fulfil ID
        $localInvoiceId = null;
        if ($fulfilInvoiceId) {
            $invoice = Invoice::where('fulfil_id', $fulfilInvoiceId)->first();
            $localInvoiceId = $invoice?->id;
        }

        EmailRecord::create([
            'fulfil_party_id' => $customerId,
            'invoice_id' => $localInvoiceId,
            'email_type' => $templateKey,
            'sent_at' => now(),
            'pdf_path' => $pdfPath,
        ]);
    }

    /**
     * Get the internal AR email address.
     */
    public function getArEmail(): string
    {
        return self::AR_EMAIL;
    }
}
