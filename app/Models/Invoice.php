<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    const STATE_DRAFT = 'draft';

    const STATE_VALIDATED = 'validated';

    const STATE_POSTED = 'posted';

    const STATE_PAID = 'paid';

    const STATE_CANCEL = 'cancel';

    protected $fillable = [
        'fulfil_id',
        'number',
        'fulfil_party_id',
        'due_date',
        'created_date',
        'last_modified_date',
        'total_amount',
        'balance',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'created_date' => 'date',
            'last_modified_date' => 'datetime',
            'total_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the customer metadata for this invoice.
     */
    public function customerMetadata(): BelongsTo
    {
        return $this->belongsTo(LocalCustomerMetadata::class, 'fulfil_party_id', 'fulfil_party_id');
    }

    /**
     * Get email records for this invoice.
     */
    public function emailRecords(): HasMany
    {
        return $this->hasMany(EmailRecord::class);
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->state === self::STATE_POSTED;
    }

    /**
     * Get days until due (negative if overdue).
     */
    public function daysUntilDue(): ?int
    {
        if (! $this->due_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->due_date, false);
    }

    /**
     * Check if invoice was modified in Fulfil since last sync.
     */
    public function wasModifiedInFulfil(\DateTimeInterface $fulfilWriteDate): bool
    {
        if (! $this->last_modified_date) {
            return true;
        }

        return $fulfilWriteDate > $this->last_modified_date;
    }

    /**
     * Sync invoice data from Fulfil API response.
     */
    public static function syncFromFulfil(array $data): self
    {
        // Ensure the customer metadata exists first (for foreign key constraint)
        LocalCustomerMetadata::findOrCreateForCustomer($data['party_id']);

        return self::updateOrCreate(
            ['fulfil_id' => $data['id']],
            [
                'number' => $data['number'],
                'fulfil_party_id' => $data['party_id'],
                'due_date' => $data['due_date'] ?? null,
                'created_date' => $data['create_date'] ?? null,
                'last_modified_date' => $data['write_date'] ?? null,
                'total_amount' => $data['total_amount'] ?? null,
                'balance' => $data['balance'] ?? null,
                'state' => $data['state'] ?? null,
            ]
        );
    }
}
