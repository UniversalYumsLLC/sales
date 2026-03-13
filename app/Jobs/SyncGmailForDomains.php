<?php

namespace App\Jobs;

use App\Models\GmailSyncHistory;
use App\Models\User;
use App\Notifications\GmailSyncResult;
use App\Services\GmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sync Gmail emails for specific domains across all Gmail-connected salespersons.
 *
 * This job is dispatched when a new prospect or customer is created (but not when
 * a prospect is promoted to customer, since emails were already synced as a prospect).
 *
 * It queries only the specified domains to avoid triggering a massive sync.
 */
class SyncGmailForDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     * Domain-specific syncs are smaller than full syncs but still need time.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param array $domains The domains to sync (e.g., ['example.com', 'company.org'])
     * @param string $entityType The type of entity ('prospect' or 'customer')
     * @param int $entityId The ID of the prospect or customer
     */
    public function __construct(
        public array $domains,
        public string $entityType,
        public int $entityId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GmailService $gmailService): void
    {
        if (empty($this->domains)) {
            Log::info('SyncGmailForDomains: No domains to sync', [
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
            ]);
            return;
        }

        // Get all salespersons with Gmail connected
        $salespersons = User::where('type', 'Salesperson')
            ->whereHas('gmailToken')
            ->get();

        if ($salespersons->isEmpty()) {
            Log::info('SyncGmailForDomains: No salespersons with Gmail connected', [
                'domains' => $this->domains,
            ]);
            return;
        }

        Log::info('SyncGmailForDomains: Starting domain sync', [
            'domains' => $this->domains,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'salespersons_count' => $salespersons->count(),
        ]);

        $now = now();
        $syncFrom = $now->copy()->subDays(config('gmail.initial_sync_days', 365));

        foreach ($salespersons as $user) {
            // Create sync history record for this user
            $syncHistory = GmailSyncHistory::create([
                'user_id' => $user->id,
                'sync_type' => GmailSyncHistory::TYPE_DOMAIN,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'domains' => $this->domains,
                'sync_started_at' => $now,
                'emails_from' => $syncFrom,
                'emails_to' => $now,
                'status' => GmailSyncHistory::STATUS_RUNNING,
            ]);

            try {
                $result = $gmailService->syncEmailsForDomains($user, $this->domains, $this->entityType, $this->entityId);

                $syncHistory->markCompleted($result['fetched'], $result['matched']);

                Log::debug('SyncGmailForDomains: Synced for user', [
                    'user_id' => $user->id,
                    'fetched' => $result['fetched'],
                    'matched' => $result['matched'],
                ]);

                // Send success notification
                $user->notify(new GmailSyncResult($syncHistory->fresh()));

            } catch (\Exception $e) {
                $syncHistory->markFailed($e->getMessage());

                Log::error('SyncGmailForDomains: Failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                // Send failure notification
                $user->notify(new GmailSyncResult($syncHistory->fresh()));

                // Continue with other users even if one fails
            }
        }

        Log::info('SyncGmailForDomains: Completed for all users', [
            'domains' => $this->domains,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ]);
    }
}
