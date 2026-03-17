<?php

namespace App\Console\Commands;

use App\Services\FulfilService;
use Illuminate\Console\Command;

class DiscoverFulfilMetafields extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fulfil:discover-metafields
                            {--fulfil-env=sandbox : Fulfil environment (sandbox or production)}';

    /**
     * The console command description.
     */
    protected $description = 'Discover metafield IDs from Fulfil for AR Automation';

    /**
     * The metafield codes we're looking for.
     */
    protected array $targetMetafields = [
        'edi' => 'EDI',
        'consolidated_invoicing' => 'Consolidated Invoicing',
        'requires_customer_skus' => 'Invoice Requires Customer SKUs',
        'invoice_discount' => 'Invoice Discount',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $environment = $this->option('fulfil-env');

        if (! in_array($environment, ['sandbox', 'production'])) {
            $this->error('Invalid environment. Use --fulfil-env=sandbox or --fulfil-env=production');

            return self::FAILURE;
        }

        $this->info("Discovering metafields from Fulfil {$environment}...");

        try {
            $service = new FulfilService($environment);

            // First, check for actual metafield values on contacts
            $this->newLine();
            $this->info('Checking contacts for metafield values...');

            $contactsWithMetafields = $service->debugContactMetafields(50);

            if (! empty($contactsWithMetafields)) {
                $this->info('Found '.count($contactsWithMetafields).' contacts with metafield values:');
                $this->newLine();

                $foundIds = [];
                foreach ($contactsWithMetafields as $contact) {
                    $this->line("Contact: {$contact['name']} (ID: {$contact['id']})");
                    foreach ($contact['metafields'] as $mf) {
                        $fieldId = $mf['field'] ?? 'N/A';
                        $code = $mf['code'] ?? 'unknown';
                        $value = $mf['value'] ?? 'null';
                        $this->line("  - Field ID: {$fieldId}, Code: {$code}, Value: {$value}");

                        // Track found metafield IDs by code
                        if (isset($this->targetMetafields[$code])) {
                            $foundIds[$code] = $fieldId;
                        }
                    }
                }

                if (! empty($foundIds)) {
                    $this->newLine();
                    $this->info('Add these to your .env file for '.$environment.':');
                    $this->newLine();

                    $prefix = $environment === 'sandbox' ? 'FULFIL_SANDBOX_METAFIELD_' : 'FULFIL_PRODUCTION_METAFIELD_';

                    foreach ($this->targetMetafields as $code => $name) {
                        $envKey = $prefix.strtoupper($code);
                        $value = $foundIds[$code] ?? '# NOT FOUND';
                        $this->line("{$envKey}={$value}");
                    }
                }
            } else {
                $this->warn('No contacts have metafield values set yet.');
                $this->line('To discover metafield IDs, set a value on at least one contact in Fulfil.');
            }

            // Also try the model field discovery approach
            $metafields = $service->discoverContactMetafields();

            if (! empty($metafields)) {
                $this->newLine();
                $this->info('Model field definitions found:');

                $headers = ['ID', 'Code', 'Name', 'Type'];
                $rows = [];

                foreach ($metafields as $mf) {
                    $rows[] = [
                        $mf['id'],
                        $mf['code'] ?? 'N/A',
                        $mf['name'] ?? 'N/A',
                        $mf['type'] ?? 'N/A',
                    ];
                }

                $this->table($headers, $rows);
            }

            // Show what's missing
            $this->newLine();
            $prefix = $environment === 'sandbox' ? 'FULFIL_SANDBOX_METAFIELD_' : 'FULFIL_PRODUCTION_METAFIELD_';
            $this->info('Required environment variables for '.$environment.':');
            foreach ($this->targetMetafields as $code => $name) {
                $envKey = $prefix.strtoupper($code);
                $this->line("  {$envKey}=<field_id>  # {$name}");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to discover metafields: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
