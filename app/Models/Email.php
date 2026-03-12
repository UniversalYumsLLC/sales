<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    const DIRECTION_INBOUND = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'user_id',
        'gmail_message_id',
        'gmail_thread_id',
        'prospect_id',
        'fulfil_party_id',
        'contact_id',
        'direction',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'subject',
        'body_text',
        'body_html',
        'email_date',
        'has_attachments',
        'attachment_info',
    ];

    protected function casts(): array
    {
        return [
            'email_date' => 'datetime',
            'has_attachments' => 'boolean',
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'attachment_info' => 'array',
        ];
    }

    /**
     * Get the user that owns the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the prospect associated with the email.
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    /**
     * Get the contact associated with the email.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ProspectContact::class, 'contact_id');
    }

    /**
     * Check if this is an inbound email (received).
     */
    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    /**
     * Check if this is an outbound email (sent).
     */
    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    /**
     * Extract domain from an email address.
     */
    public static function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }
}
