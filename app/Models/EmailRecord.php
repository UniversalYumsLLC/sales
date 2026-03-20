<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailRecord extends Model
{
    use HasFactory;

    // Email type constants
    const TYPE_INITIAL_INVOICE = 'initial_invoice';

    const TYPE_INITIAL_INVOICE_AP_PORTAL = 'initial_invoice_ap_portal';

    const TYPE_INVOICE_MODIFIED = 'invoice_modified';

    const TYPE_INVOICE_MODIFIED_AP_PORTAL = 'invoice_modified_ap_portal';

    const TYPE_DUE_REMINDER = 'due_reminder';

    const TYPE_OVERDUE_NOTIFICATION = 'overdue_notification';

    const TYPE_OVERDUE_FOLLOWUP = 'overdue_followup';

    const TYPE_SKU_MAPPING_ERROR = 'sku_mapping_error';

    /**
     * Get all valid email types.
     */
    public static function getEmailTypes(): array
    {
        return [
            self::TYPE_INITIAL_INVOICE,
            self::TYPE_INITIAL_INVOICE_AP_PORTAL,
            self::TYPE_INVOICE_MODIFIED,
            self::TYPE_INVOICE_MODIFIED_AP_PORTAL,
            self::TYPE_DUE_REMINDER,
            self::TYPE_OVERDUE_NOTIFICATION,
            self::TYPE_OVERDUE_FOLLOWUP,
            self::TYPE_SKU_MAPPING_ERROR,
        ];
    }

    protected $fillable = [
        'fulfil_party_id',
        'invoice_id',
        'email_type',
        'sent_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the customer metadata for this email record.
     */
    public function customerMetadata(): BelongsTo
    {
        return $this->belongsTo(LocalCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get the invoice for this email record.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if an email of the given type(s) was already sent for an invoice.
     */
    public static function wasEmailSent(int $invoiceId, string|array $emailTypes): bool
    {
        $types = (array) $emailTypes;

        return self::where('invoice_id', $invoiceId)
            ->whereIn('email_type', $types)
            ->exists();
    }

    /**
     * Get the last email record of a given type for an invoice.
     */
    public static function getLastEmailOfType(int $invoiceId, string $emailType): ?self
    {
        return self::where('invoice_id', $invoiceId)
            ->where('email_type', $emailType)
            ->latest('sent_at')
            ->first();
    }

    /**
     * Check if initial invoice email was sent (either regular or AP portal).
     */
    public static function wasInitialInvoiceSent(int $invoiceId): bool
    {
        return self::wasEmailSent($invoiceId, [
            self::TYPE_INITIAL_INVOICE,
            self::TYPE_INITIAL_INVOICE_AP_PORTAL,
        ]);
    }

    /**
     * Get the most recent PDF path for an invoice.
     */
    public static function getLatestPdfPath(int $invoiceId): ?string
    {
        $record = self::where('invoice_id', $invoiceId)
            ->whereNotNull('pdf_path')
            ->latest('sent_at')
            ->first();

        return $record?->pdf_path;
    }
}
