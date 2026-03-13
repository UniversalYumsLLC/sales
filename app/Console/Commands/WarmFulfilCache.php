<?php

namespace App\Console\Commands;

use App\Services\FulfilService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmFulfilCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fulfil:warm-cache {--force : Force cache refresh even if cached}';

    /**
     * The console command description.
     */
    protected $description = 'Pre-warm the Fulfil API cache to prevent cold-start timeouts';

    /**
     * Execute the console command.
     */
    public function handle(FulfilService $fulfil): int
    {
        $this->info('Warming Fulfil cache...');
        $startTime = microtime(true);

        $bustCache = $this->option('force');

        try {
            // 1. Active Customers
            $this->info('  Warming active customers...');
            $start = microtime(true);
            $customers = $fulfil->getActiveCustomers($bustCache);
            $duration = round(microtime(true) - $start, 2);
            $this->info("    -> {$duration}s ({$this->count($customers)} records)");

            // 2. Sales Orders (done, last 2 years)
            $this->info('  Warming sales orders (done, 2 years)...');
            $start = microtime(true);
            $doneOrders = $fulfil->getSalesOrders([
                'state' => ['done'],
                'shipping_date_from' => Carbon::now()->subYears(2)->toDateString(),
            ], $bustCache);
            $duration = round(microtime(true) - $start, 2);
            $this->info("    -> {$duration}s ({$this->count($doneOrders)} records)");

            // 3. Sales Orders (open)
            $this->info('  Warming sales orders (open)...');
            $start = microtime(true);
            $openOrders = $fulfil->getSalesOrders([
                'state' => ['confirmed', 'processing'],
            ], $bustCache);
            $duration = round(microtime(true) - $start, 2);
            $this->info("    -> {$duration}s ({$this->count($openOrders)} records)");

            // 4. Invoices
            $this->info('  Warming invoices...');
            $start = microtime(true);
            $invoices = $fulfil->getInvoices([
                'state' => ['validated', 'posted'],
            ], $bustCache);
            $duration = round(microtime(true) - $start, 2);
            $this->info("    -> {$duration}s ({$this->count($invoices)} records)");

            $totalDuration = round(microtime(true) - $startTime, 2);
            $this->info("Cache warming complete in {$totalDuration}s");

            Log::info('Fulfil cache warmed successfully', [
                'duration' => $totalDuration,
                'forced' => $bustCache,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cache warming failed: {$e->getMessage()}");

            Log::error('Fulfil cache warming failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function count(mixed $data): int
    {
        return is_array($data) ? count($data) : 0;
    }
}
