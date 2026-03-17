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
     * @param  array  $emails  List of email addresses
     * @return array Filtered list (only allowed emails in test mode)
     */
    public function filterEmails(array $emails): array
    {
        if (! $this->isEnabled()) {
            return $emails;
        }

        return array_values(array_filter($emails, fn ($email) => $this->canSendEmailTo($email)));
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
        Log::warning('AR Test Mode ENABLED - Fulfil will use sandbox, emails restricted to @universalyums.com');
    }

    /**
     * Disable test mode.
     */
    public function disable(): void
    {
        Setting::setTestMode(false);
        Log::warning('AR Test Mode DISABLED - Fulfil will use production, emails unrestricted');
    }

    /**
     * Get the allowed email domain in test mode.
     */
    public function getAllowedDomain(): string
    {
        return self::TEST_MODE_ALLOWED_DOMAIN;
    }
}
