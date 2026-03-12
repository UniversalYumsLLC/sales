<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfilContactMetadata extends Model
{
    protected $table = 'fulfil_contact_metadata';

    protected $fillable = [
        'fulfil_party_id',
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
     * Get or create metadata for a contact email.
     */
    public static function findOrCreateForContact(int $fulfilPartyId, string $email): self
    {
        return self::firstOrCreate(
            [
                'fulfil_party_id' => $fulfilPartyId,
                'email' => strtolower($email),
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
     * Get the customer metadata this belongs to.
     */
    public function customerMetadata()
    {
        return $this->belongsTo(FulfilCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }
}
