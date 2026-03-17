<?php

namespace App\Jobs;

use App\Services\ArAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessArInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 600]; // 1min, 5min, 10min

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ArAutomationService $service): void
    {
        Log::info('AR Automation: Starting daily invoice processing job');

        $startTime = microtime(true);

        try {
            $result = $service->processAllInvoices();

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('AR Automation: Job completed successfully', [
                'duration_seconds' => $duration,
                'invoices_processed' => $result['processed'] ?? 0,
                'emails_sent' => $result['emails_sent'] ?? 0,
                'errors' => $result['errors'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);

            Log::error('AR Automation: Job failed', [
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::critical('AR Automation: Job failed after all retries', [
            'error' => $exception?->getMessage(),
        ]);

        // TODO: Optionally send notification to admins
    }
}
