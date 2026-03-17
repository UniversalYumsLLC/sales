<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Cache TTL for settings (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Get a setting value by key.
     *
     * @param  string  $key  The setting key
     * @param  mixed  $default  Default value if not found
     * @return mixed The setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting_{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting ? self::castValue($setting->value, $key) : $default;
        });
    }

    /**
     * Set a setting value.
     *
     * @param  string  $key  The setting key
     * @param  mixed  $value  The value to set
     */
    public static function set(string $key, mixed $value): void
    {
        // Convert booleans to string representation
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Clear the cache for this setting
        Cache::forget("setting_{$key}");
    }

    /**
     * Cast value based on setting key or value content.
     */
    protected static function castValue(mixed $value, string $key): mixed
    {
        if ($value === null) {
            return null;
        }

        // Boolean settings
        $booleanSettings = ['ar_test_mode'];
        if (in_array($key, $booleanSettings)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Auto-detect booleans
        if ($value === 'true' || $value === 'false') {
            return $value === 'true';
        }

        // Auto-detect integers
        if (is_numeric($value) && ! str_contains($value, '.')) {
            return (int) $value;
        }

        // Auto-detect floats
        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Check if AR Test Mode is enabled.
     */
    public static function isTestModeEnabled(): bool
    {
        return (bool) self::get('ar_test_mode', false);
    }

    /**
     * Enable or disable AR Test Mode.
     */
    public static function setTestMode(bool $enabled): void
    {
        self::set('ar_test_mode', $enabled);
    }
}
