<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfilUncategorizedContact extends Model
{
    protected $table = 'fulfil_uncategorized_contacts';

    const TYPE_BUYER = 'buyer';
    const TYPE_ACCOUNTS_PAYABLE = 'accounts_payable';
    const TYPE_LOGISTICS = 'logistics';

    /**
     * Contact types that can be assigned.
     */
    const CATEGORIZABLE_TYPES = [
        self::TYPE_BUYER,
        self::TYPE_ACCOUNTS_PAYABLE,
        self::TYPE_LOGISTICS,
    ];

    protected $fillable = [
        'fulfil_party_id',
        'name',
        'email',
        'type',
        'last_emailed_at',
        'last_received_at',
    ];

    protected function casts(): array
    {
        return [
            'last_emailed_at' => 'datetime',
            'last_received_at' => 'datetime',
        ];
    }

    /**
     * Get the customer metadata this contact belongs to.
     */
    public function customerMetadata(): BelongsTo
    {
        return $this->belongsTo(FulfilCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get or create an uncategorized contact for a customer email.
     */
    public static function findOrCreateForContact(int $fulfilPartyId, string $email): self
    {
        return self::firstOrCreate(
            [
                'fulfil_party_id' => $fulfilPartyId,
                'email' => strtolower($email),
            ],
            [
                'name' => '',
            ]
        );
    }

    /**
     * Record that an email was sent to this contact.
     */
    public function recordEmailSent(\DateTime $date): void
    {
        if (!$this->last_emailed_at || $date > $this->last_emailed_at) {
            $this->last_emailed_at = $date;
            $this->save();
        }
    }

    /**
     * Record that an email was received from this contact.
     */
    public function recordEmailReceived(\DateTime $date): void
    {
        if (!$this->last_received_at || $date > $this->last_received_at) {
            $this->last_received_at = $date;
            $this->save();
        }
    }

    /**
     * Get the email domain from this contact's email.
     */
    public function getEmailDomain(): ?string
    {
        if (empty($this->email)) {
            return null;
        }
        $parts = explode('@', $this->email);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }
}
