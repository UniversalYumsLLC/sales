<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfilBrokerContact extends Model
{
    protected $table = 'fulfil_broker_contacts';

    protected $fillable = [
        'fulfil_party_id',
        'name',
        'email',
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
     * Get or create a broker contact for a customer.
     */
    public static function findOrCreateForContact(int $fulfilPartyId, string $email, string $name = ''): self
    {
        return self::firstOrCreate(
            [
                'fulfil_party_id' => $fulfilPartyId,
                'email' => strtolower($email),
            ],
            [
                'name' => $name,
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
     * Format the name for Fulfil sync (with "Broker: " prefix).
     */
    public function getFulfilName(): string
    {
        return 'Broker: '.$this->name;
    }

    /**
     * Parse a Fulfil contact name to extract the broker name.
     */
    public static function parseFulfilName(string $fulfilName): ?string
    {
        if (str_starts_with($fulfilName, 'Broker: ')) {
            return substr($fulfilName, 8); // Remove "Broker: " prefix
        }

        return null;
    }
}
