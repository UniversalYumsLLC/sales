<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class TestModeService
{
    /**
     * The allowed email domain in test mode.
     */
    protected const TEST_MODE_ALLOWED_DOMAIN = '@universalyums.com';

    /**
     * The redirect email address for test mode.
     */
    protected const TEST_MODE_REDIRECT_EMAIL = 'accountsreceivable@universalyums.com';

    /**
     * Check if AR Test Mode is enabled.
     */
    public function isEnabled(): bool
    {
        return Setting::isTestModeEnabled();
    }

    /**
     * Check if an email can be sent to the given address.
     *
     * In test mode, only @universalyums.com addresses are allowed.
     */
    public function canSendEmailTo(string $email): bool
    {
        if (! $this->isEnabled()) {
            return true; // Normal mode, send to anyone
        }

        // Test mode: only allow @universalyums.com
        $allowed = str_ends_with(strtolower($email), self::TEST_MODE_ALLOWED_DOMAIN);

        if (! $allowed) {
            Log::info('Test mode: blocking email to external address', [
                'email' => $email,
                'reason' => 'test_mode_enabled',
            ]);
        }

        return $allowed;
    }

    /**
     * Filter a list of email addresses based on test mode.
     *
     * In test mode, external emails are redirected to the test email address.
     *
     * @param  array  $emails  List of email addresses
     * @return array Filtered/redirected list
     */
    public function filterEmails(array $emails): array
    {
        if (! $this->isEnabled()) {
            return $emails;
        }

        // In test mode, if there are any external emails, redirect to test email
        $hasExternalEmails = false;
        $allowedEmails = [];

        foreach ($emails as $email) {
            if ($this->canSendEmailTo($email)) {
                $allowedEmails[] = $email;
            } else {
                $hasExternalEmails = true;
            }
        }

        // If we had external emails that got blocked, redirect to test email
        if ($hasExternalEmails) {
            Log::info('Test mode: redirecting external emails to test address', [
                'original_count' => count($emails),
                'redirect_to' => self::TEST_MODE_REDIRECT_EMAIL,
            ]);

            // Add the redirect email if not already present
            if (! in_array(self::TEST_MODE_REDIRECT_EMAIL, $allowedEmails)) {
                $allowedEmails[] = self::TEST_MODE_REDIRECT_EMAIL;
            }
        }

        return array_values(array_unique($allowedEmails));
    }

    /**
     * Get the redirect email address for test mode.
     */
    public function getRedirectEmail(): string
    {
        return self::TEST_MODE_REDIRECT_EMAIL;
    }

    /**
     * Get the Fulfil environment to use.
     *
     * In test mode, always use sandbox.
     */
    public function getFulfilEnvironment(): string
    {
        return $this->isEnabled() ? 'sandbox' : 'production';
    }

    /**
     * Enable test mode.
     */
    public function enable(): void
    {
        Setting::setTestMode(true);
        $this->clearFulfilCache();
        Log::warning('AR Test Mode ENABLED - Fulfil will use sandbox, emails restricted to @universalyums.com');
    }

    /**
     * Disable test mode.
     */
    public function disable(): void
    {
        Setting::setTestMode(false);
        $this->clearFulfilCache();
        Log::warning('AR Test Mode DISABLED - Fulfil will use production, emails unrestricted');
    }

    /**
     * Clear all Fulfil-related cache when switching environments.
     */
    protected function clearFulfilCache(): void
    {
        $laravelPrefix = config('cache.prefix', '');
        $fulfilPrefix = config('fulfil.cache.prefix', 'fulfil_');
        $fullPrefix = $laravelPrefix.$fulfilPrefix;

        \DB::table('cache')->where('key', 'like', $fullPrefix.'%')->delete();

        Log::info('Cleared Fulfil cache due to test mode toggle');
    }

    /**
     * Get the allowed email domain in test mode.
     */
    public function getAllowedDomain(): string
    {
        return self::TEST_MODE_ALLOWED_DOMAIN;
    }
}
