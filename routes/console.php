<?php

use App\Jobs\ProcessArInvoicesJob;
use App\Services\ArAutomationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AR Automation - manual trigger command
Artisan::command('ar:process-invoices {--sync : Run synchronously instead of queuing}', function (ArAutomationService $service) {
    $this->info('Starting AR Automation invoice processing...');

    if ($this->option('sync')) {
        $this->info('Running synchronously...');
        $result = $service->processAllInvoices();
        $this->info("Processed: {$result['processed']} invoices");
        $this->info("Emails sent: {$result['emails_sent']}");
        if ($result['errors'] > 0) {
            $this->warn("Errors: {$result['errors']}");
        }
    } else {
        ProcessArInvoicesJob::dispatch();
        $this->info('Job dispatched to queue.');
    }
})->purpose('Process AR invoices and send automated emails');

// Gmail sync - runs every 15 minutes
Schedule::command('gmail:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/gmail-sync.log'));

// Fulfil cache warming - runs every 45 minutes (cache TTL is 60 min)
// Keeps cache warm to prevent cold-start timeouts on Active Customers page
Schedule::command('fulfil:warm-cache')
    ->cron('*/45 * * * *')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/fulfil-cache.log'));

// AR Automation - runs daily at 2:00 AM EST
// Processes posted invoices and sends automated emails
Schedule::job(new ProcessArInvoicesJob)
    ->dailyAt('02:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ar-automation.log'));
