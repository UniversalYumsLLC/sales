<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GmailSyncHistory extends Model
{
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const TYPE_FULL = 'full';
    const TYPE_DOMAIN = 'domain';

    protected $table = 'gmail_sync_history';

    protected $fillable = [
        'user_id',
        'sync_type',
        'entity_type',
        'entity_id',
        'domains',
        'sync_started_at',
        'sync_completed_at',
        'emails_from',
        'emails_to',
        'emails_fetched',
        'emails_matched',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sync_started_at' => 'datetime',
            'sync_completed_at' => 'datetime',
            'emails_from' => 'datetime',
            'emails_to' => 'datetime',
            'domains' => 'array',
        ];
    }

    /**
     * Check if this is a domain-specific sync.
     */
    public function isDomainSync(): bool
    {
        return $this->sync_type === self::TYPE_DOMAIN;
    }

    /**
     * Get a human-readable description of what was synced.
     */
    public function getSyncDescription(): string
    {
        if ($this->sync_type === self::TYPE_FULL) {
            return 'Full sync';
        }

        $entityLabel = $this->entity_type === 'prospect' ? 'prospect' : 'customer';
        $domains = $this->domains ? implode(', ', $this->domains) : 'unknown';

        return "Domain sync for {$entityLabel} ({$domains})";
    }

    /**
     * Get the user that owns the sync history.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the sync as completed.
     */
    public function markCompleted(int $fetched = 0, int $matched = 0): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'sync_completed_at' => now(),
            'emails_fetched' => $fetched,
            'emails_matched' => $matched,
        ]);
    }

    /**
     * Mark the sync as failed.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'sync_completed_at' => now(),
            'error_message' => $error,
        ]);
    }
}
