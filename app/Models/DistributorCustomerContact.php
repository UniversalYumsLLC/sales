<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorCustomerContact extends Model
{
    const TYPE_BUYER = 'buyer';

    const TYPE_ACCOUNTS_PAYABLE = 'accounts_payable';

    const TYPE_OTHER = 'other';

    const TYPE_UNCATEGORIZED = 'uncategorized';

    /**
     * Contact types that can be assigned.
     */
    const CATEGORIZABLE_TYPES = [
        self::TYPE_BUYER,
        self::TYPE_ACCOUNTS_PAYABLE,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'distributor_customer_id',
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
     * Get the distributor customer this contact belongs to.
     */
    public function distributorCustomer(): BelongsTo
    {
        return $this->belongsTo(DistributorCustomer::class);
    }

    /**
     * Get or create a contact for a distributor customer email.
     */
    public static function findOrCreateForContact(int $distributorCustomerId, string $email, string $name = ''): self
    {
        return self::firstOrCreate(
            [
                'distributor_customer_id' => $distributorCustomerId,
                'email' => strtolower($email),
            ],
            [
                'name' => $name,
                'type' => self::TYPE_UNCATEGORIZED,
            ]
        );
    }

    /**
     * Record that an email was sent to this contact.
     */
    public function recordEmailSent(\DateTime $date): void
    {
        if (! $this->last_emailed_at || $date > $this->last_emailed_at) {
            $this->last_emailed_at = $date;
            $this->save();
        }
    }

    /**
     * Record that an email was received from this contact.
     */
    public function recordEmailReceived(\DateTime $date): void
    {
        if (! $this->last_received_at || $date > $this->last_received_at) {
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
