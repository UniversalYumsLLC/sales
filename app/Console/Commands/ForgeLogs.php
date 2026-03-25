<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ForgeLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'forge:logs
        {--lines=200 : Number of lines to retrieve}
        {--grep= : Filter log output by a search term}
        {--file=storage/logs/laravel.log : Log file path (absolute or relative to site home)}';

    /**
     * The console command description.
     */
    protected $description = 'Retrieve production site logs from Laravel Forge';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = config('services.forge.token');
        $serverId = config('services.forge.server_id');
        $siteId = config('services.forge.site_id');

        if (! $token) {
            $this->error('FORGE_API_TOKEN is not set. Add it to your .env file.');

            return Command::FAILURE;
        }

        $lines = (int) $this->option('lines');
        $file = $this->option('file');
        $grep = $this->option('grep');

        // Commands run from /home/forge/sales.yums.com/current (symlink).
        // The shared storage lives at /home/forge/sales.yums.com/storage,
        // so we resolve relative paths from the site home directory.
        if (! str_starts_with($file, '/')) {
            $file = "/home/forge/sales.yums.com/{$file}";
        }

        $shellCommand = "tail -n {$lines} {$file}";
        if ($grep) {
            $shellCommand .= " | grep -i " . escapeshellarg($grep);
        }

        $this->info("Running: {$shellCommand}");
        $this->newLine();

        try {
            // Submit the command
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
                'User-Agent' => 'Forge-CLI/1.0',
            ])->post("https://forge.laravel.com/api/v1/servers/{$serverId}/sites/{$siteId}/commands", [
                'command' => $shellCommand,
            ]);

            if ($response->failed()) {
                $this->error("Failed to submit command: HTTP {$response->status()}");
                $this->error($response->body());

                return Command::FAILURE;
            }

            $commandId = $response->json('command.id');
            $this->info('Command submitted, waiting for output...');

            // Poll for completion
            $maxAttempts = 20;
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(2);

                $result = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'User-Agent' => 'Forge-CLI/1.0',
                ])->get("https://forge.laravel.com/api/v1/servers/{$serverId}/sites/{$siteId}/commands/{$commandId}");

                if ($result->failed()) {
                    $this->error("Failed to fetch result: HTTP {$result->status()}");

                    return Command::FAILURE;
                }

                $status = $result->json('command.status');

                if ($status === 'finished') {
                    $output = $result->json('output');
                    if ($output) {
                        $this->line($output);
                    } else {
                        $this->warn('Command completed but returned no output.');
                    }

                    return Command::SUCCESS;
                }

                if ($status === 'failed') {
                    $this->error('Command failed on server.');
                    $output = $result->json('output');
                    if ($output) {
                        $this->line($output);
                    }

                    return Command::FAILURE;
                }
            }

            $this->error('Timed out waiting for command to complete.');

            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
