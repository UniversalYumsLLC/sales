<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectContact extends Model
{
    use HasFactory;

    const TYPE_BUYER = 'buyer';
    const TYPE_ACCOUNTS_PAYABLE = 'accounts_payable';
    const TYPE_LOGISTICS = 'logistics';

    protected $fillable = [
        'prospect_id',
        'type',
        'name',
        'value',
        'last_emailed_at',
        'last_received_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_emailed_at' => 'datetime',
            'last_received_at' => 'datetime',
        ];
    }

    /**
     * Get the email domain from this contact's value field.
     */
    public function getEmailDomain(): ?string
    {
        if (empty($this->value) || !filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $parts = explode('@', $this->value);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }

    /**
     * Update the last emailed timestamp.
     */
    public function recordEmailSent(\DateTime $date): void
    {
        if ($this->last_emailed_at === null || $date > $this->last_emailed_at) {
            $this->update(['last_emailed_at' => $date]);
        }
    }

    /**
     * Update the last received email timestamp.
     */
    public function recordEmailReceived(\DateTime $date): void
    {
        if ($this->last_received_at === null || $date > $this->last_received_at) {
            $this->update(['last_received_at' => $date]);
        }
    }

    /**
     * Get the prospect this contact belongs to.
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
