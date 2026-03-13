<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGmailEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:sync {--user= : Sync only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from Gmail for all connected salesperson users';

    /**
     * Execute the console command.
     */
    public function handle(GmailService $gmail): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $users = User::where('id', $userId)
                ->where('role', User::ROLE_SALESPERSON)
                ->whereHas('gmailToken')
                ->get();

            if ($users->isEmpty()) {
                $this->error("No salesperson user found with ID {$userId} and Gmail connected.");

                return self::FAILURE;
            }
        } else {
            // Get all salesperson users with Gmail connected
            $users = User::where('role', User::ROLE_SALESPERSON)
                ->whereHas('gmailToken')
                ->get();
        }

        if ($users->isEmpty()) {
            $this->info('No salesperson users with Gmail connected.');

            return self::SUCCESS;
        }

        $this->info("Syncing Gmail for {$users->count()} user(s)...");

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            $this->line("  Syncing for {$user->name} ({$user->email})...");

            try {
                $syncHistory = $gmail->syncEmails($user);

                if ($syncHistory->status === 'completed') {
                    $this->info("    Completed: {$syncHistory->emails_fetched} fetched, {$syncHistory->emails_matched} matched");
                    $successCount++;
                } else {
                    $this->error("    Failed: {$syncHistory->error_message}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("    Error: {$e->getMessage()}");
                Log::error('Gmail sync command failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Sync complete: {$successCount} succeeded, {$failCount} failed");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
