<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
