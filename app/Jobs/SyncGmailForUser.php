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
 * Perform a full Gmail sync for a single user.
 *
 * This job is dispatched when a salesperson first connects their Gmail account
 * or triggers a manual sync. It performs the sync which goes back 365 days,
 * so it runs as a background job to avoid blocking the user.
 */
class SyncGmailForUser implements ShouldQueue
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
     * Initial sync can take a while with 365 days of emails.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     *
     * @param  int  $userId  The user ID to sync
     * @param  bool  $forceFullSync  If true, ignores last sync and goes back full 365 days
     */
    public function __construct(
        public int $userId,
        public bool $forceFullSync = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GmailService $gmailService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('SyncGmailForUser: User not found', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        if (! $user->gmailToken) {
            Log::warning('SyncGmailForUser: User does not have Gmail connected', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        Log::info('SyncGmailForUser: Starting sync', [
            'user_id' => $user->id,
            'gmail_email' => $user->gmailToken->gmail_email,
            'force_full_sync' => $this->forceFullSync,
        ]);

        try {
            $syncHistory = $gmailService->syncEmails($user, $this->forceFullSync);

            Log::info('SyncGmailForUser: Completed', [
                'user_id' => $user->id,
                'status' => $syncHistory->status,
                'fetched' => $syncHistory->emails_fetched,
                'matched' => $syncHistory->emails_matched,
            ]);

            // Send notification with results
            $user->notify(new GmailSyncResult($syncHistory));

        } catch (\Exception $e) {
            Log::error('SyncGmailForUser: Failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // The syncHistory might have been created and marked as failed by GmailService
            // Try to find it and send a notification
            $syncHistory = GmailSyncHistory::where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($syncHistory && $syncHistory->status === GmailSyncHistory::STATUS_FAILED) {
                $user->notify(new GmailSyncResult($syncHistory));
            }

            throw $e; // Re-throw to trigger retry
        }
    }
}
