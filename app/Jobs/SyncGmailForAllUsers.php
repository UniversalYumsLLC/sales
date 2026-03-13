<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatch full Gmail sync jobs for all Gmail-connected salespersons.
 *
 * This job is triggered by admins to initiate a full 365-day resync
 * across all salespersons. It dispatches individual SyncGmailForUser
 * jobs for each salesperson.
 */
class SyncGmailForAllUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param bool $forceFullSync If true, each user sync will go back full 365 days
     */
    public function __construct(
        public bool $forceFullSync = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get all salespersons with Gmail connected
        $salespersons = User::where('role', User::ROLE_SALESPERSON)
            ->whereHas('gmailToken')
            ->get();

        if ($salespersons->isEmpty()) {
            Log::info('SyncGmailForAllUsers: No salespersons with Gmail connected');
            return;
        }

        Log::info('SyncGmailForAllUsers: Dispatching sync jobs', [
            'salespersons_count' => $salespersons->count(),
            'force_full_sync' => $this->forceFullSync,
        ]);

        // Dispatch individual sync jobs for each salesperson
        foreach ($salespersons as $user) {
            SyncGmailForUser::dispatch($user->id, $this->forceFullSync);

            Log::debug('SyncGmailForAllUsers: Dispatched sync job', [
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);
        }

        Log::info('SyncGmailForAllUsers: All sync jobs dispatched');
    }
}
