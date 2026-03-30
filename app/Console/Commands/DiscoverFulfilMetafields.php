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
    protected $description = 'Discover metafield IDs from Fulfil for contact metafields';

    /**
     * The metafield codes we're looking for.
     * Maps internal code => Fulfil metafield display name.
     *
     * Only metafields still stored in Fulfil are listed here.
     * edi, consolidated_invoicing, requires_customer_skus, broker, and
     * broker_commission have been migrated to local_customer_metadata.
     */
    protected array $targetMetafields = [
        'invoice_discount' => 'Invoice Discount',
        'shelf_life' => 'Shelf Life Required on Arrival (Days)',
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

            // Query the metafield.field model directly for definitions
            $this->newLine();
            $this->info('Querying metafield.field model for definitions...');

            try {
                $allMetafields = $service->getMetafieldDefinitions();

                if (! empty($allMetafields)) {
                    // Match by name to our target metafields
                    $nameToCode = array_flip($this->targetMetafields);

                    foreach ($allMetafields as $mf) {
                        $name = $mf['rec_name'] ?? '';
                        if (isset($nameToCode[$name])) {
                            $foundIds[$nameToCode[$name]] = $mf['id'];
                        }
                    }

                    if (! empty($foundIds)) {
                        $this->info('Found metafield IDs from definitions:');
                        $this->newLine();

                        $prefix = $environment === 'sandbox' ? 'FULFIL_SANDBOX_METAFIELD_' : 'FULFIL_PRODUCTION_METAFIELD_';
                        foreach ($this->targetMetafields as $code => $name) {
                            $envKey = $prefix.strtoupper($code);
                            $value = $foundIds[$code] ?? '# NOT FOUND';
                            $this->line("{$envKey}={$value}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->warn('Could not query metafield.field model: '.$e->getMessage());
            }

            // Show what's missing
            $this->newLine();
            $prefix = $environment === 'sandbox' ? 'FULFIL_SANDBOX_METAFIELD_' : 'FULFIL_PRODUCTION_METAFIELD_';
            $this->info('Required environment variables for '.$environment.':');
            foreach ($this->targetMetafields as $code => $name) {
                $envKey = $prefix.strtoupper($code);
                $currentValue = $foundIds[$code] ?? null;
                $status = $currentValue ? " (found: {$currentValue})" : ' # NOT FOUND';
                $this->line("  {$envKey}=<field_id>{$status}  # {$name}");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to discover metafields: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
