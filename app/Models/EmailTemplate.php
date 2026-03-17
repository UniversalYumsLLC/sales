<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    // Template type constants
    public const TYPE_SKU_MAPPING_ERROR = 'sku_mapping_error';

    public const TYPE_INITIAL_INVOICE = 'initial_invoice';

    public const TYPE_INITIAL_INVOICE_AP_PORTAL = 'initial_invoice_ap_portal';

    public const TYPE_INVOICE_MODIFIED = 'invoice_modified';

    public const TYPE_INVOICE_MODIFIED_AP_PORTAL = 'invoice_modified_ap_portal';

    public const TYPE_DUE_REMINDER = 'due_reminder';

    public const TYPE_OVERDUE_NOTIFICATION = 'overdue_notification';

    public const TYPE_OVERDUE_FOLLOWUP = 'overdue_followup';

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body',
    ];

    /**
     * Get all template types with their metadata.
     */
    public static function getTemplateTypes(): array
    {
        return [
            self::TYPE_SKU_MAPPING_ERROR => [
                'name' => 'SKU Mapping Error',
                'description' => 'Internal alert when SKU mapping is missing',
                'recipient' => 'internal',
                'placeholders' => ['customer_name', 'missing_skus'],
            ],
            self::TYPE_INITIAL_INVOICE => [
                'name' => 'Initial Invoice',
                'description' => 'First invoice sent to customer',
                'recipient' => 'customer_ap',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due'],
            ],
            self::TYPE_INITIAL_INVOICE_AP_PORTAL => [
                'name' => 'Initial Invoice - AP Portal',
                'description' => 'First invoice notification for portal customers',
                'recipient' => 'internal',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due', 'ap_portal_url'],
            ],
            self::TYPE_INVOICE_MODIFIED => [
                'name' => 'Invoice Modified',
                'description' => 'Invoice was modified after initial send',
                'recipient' => 'customer_ap',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due'],
            ],
            self::TYPE_INVOICE_MODIFIED_AP_PORTAL => [
                'name' => 'Invoice Modified - AP Portal',
                'description' => 'Modified invoice for portal customers',
                'recipient' => 'internal',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due', 'ap_portal_url'],
            ],
            self::TYPE_DUE_REMINDER => [
                'name' => 'Due Reminder',
                'description' => '7 days before due date',
                'recipient' => 'customer_ap',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due'],
            ],
            self::TYPE_OVERDUE_NOTIFICATION => [
                'name' => 'Overdue Notification',
                'description' => '1 day after due date',
                'recipient' => 'customer_ap',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due'],
            ],
            self::TYPE_OVERDUE_FOLLOWUP => [
                'name' => 'Overdue Follow-up',
                'description' => 'Weekly follow-up for overdue invoices',
                'recipient' => 'customer_ap',
                'placeholders' => ['customer_name', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due'],
            ],
        ];
    }

    /**
     * Get all valid template keys.
     */
    public static function getValidKeys(): array
    {
        return array_keys(self::getTemplateTypes());
    }

    /**
     * Get a template by its key.
     */
    public static function getByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    /**
     * Get available placeholders for this template.
     */
    public function getPlaceholders(): array
    {
        $types = self::getTemplateTypes();

        return $types[$this->key]['placeholders'] ?? [];
    }

    /**
     * Check if this template sends to internal recipients.
     */
    public function isInternalTemplate(): bool
    {
        $types = self::getTemplateTypes();

        return ($types[$this->key]['recipient'] ?? '') === 'internal';
    }

    /**
     * Replace placeholders in subject and body.
     *
     * @param  array  $data  Key-value pairs for placeholder replacement
     * @return array ['subject' => string, 'body' => string]
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
