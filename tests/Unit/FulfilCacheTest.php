<?php

use App\Services\FulfilService;
use App\Services\TestModeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Configure both Fulfil environments for testing
    config([
        'fulfil.default' => 'sandbox',
        'fulfil.environments.sandbox.subdomain' => 'test-sandbox',
        'fulfil.environments.sandbox.token' => 'sandbox-token',
        'fulfil.environments.production.subdomain' => 'test-production',
        'fulfil.environments.production.token' => 'production-token',
        'fulfil.cache.prefix' => 'fulfil_',
        'fulfil.cache.ttl' => 3600,
    ]);

    // Prevent actual HTTP requests
    Http::fake();
});

test('cache prefix includes the environment name', function () {
    $sandbox = new FulfilService('sandbox');
    $production = new FulfilService('production');

    // Use reflection to read the protected cachePrefix property
    $ref = new ReflectionClass(FulfilService::class);
    $prop = $ref->getProperty('cachePrefix');
    $prop->setAccessible(true);

    expect($prop->getValue($sandbox))->toBe('fulfil_sandbox_');
    expect($prop->getValue($production))->toBe('fulfil_production_');
});

test('clearCache does not flush the entire cache store', function () {
    // Switch to database cache driver for this test
    config(['cache.default' => 'database']);

    $laravelPrefix = config('cache.prefix', '');

    // Insert entries directly into the cache table
    DB::table('cache')->insert([
        ['key' => $laravelPrefix.'unrelated_app_key', 'value' => serialize('important_data'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_sandbox_active_customers', 'value' => serialize(['customer1']), 'expiration' => time() + 3600],
    ]);

    // Clear Fulfil cache
    $service = new FulfilService('sandbox');
    $service->clearCache();

    $remainingKeys = DB::table('cache')->pluck('key')->toArray();

    // The unrelated key should still exist
    expect($remainingKeys)->toContain($laravelPrefix.'unrelated_app_key');

    // The Fulfil key should be gone
    expect($remainingKeys)->not->toContain($laravelPrefix.'fulfil_sandbox_active_customers');
});

test('clearCache only clears keys for the current environment', function () {
    // Switch to database cache driver for this test
    config(['cache.default' => 'database']);

    $laravelPrefix = config('cache.prefix', '');

    // Insert cache entries for both environments
    DB::table('cache')->insert([
        ['key' => $laravelPrefix.'fulfil_sandbox_active_customers', 'value' => serialize('sandbox_data'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_production_active_customers', 'value' => serialize('prod_data'), 'expiration' => time() + 3600],
    ]);

    // Clear only sandbox cache
    $sandbox = new FulfilService('sandbox');
    $sandbox->clearCache();

    $remainingKeys = DB::table('cache')->pluck('key')->toArray();

    // Sandbox key should be gone
    expect($remainingKeys)->not->toContain($laravelPrefix.'fulfil_sandbox_active_customers');

    // Production key should still exist
    expect($remainingKeys)->toContain($laravelPrefix.'fulfil_production_active_customers');
});

test('clearCache with a specific key only clears that key', function () {
    // Switch to database cache driver for this test
    config(['cache.default' => 'database']);

    $laravelPrefix = config('cache.prefix', '');

    DB::table('cache')->insert([
        ['key' => $laravelPrefix.'fulfil_sandbox_active_customers', 'value' => serialize('customers'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_sandbox_products', 'value' => serialize('products'), 'expiration' => time() + 3600],
    ]);

    $service = new FulfilService('sandbox');
    $service->clearCache('active_customers');

    $remainingKeys = DB::table('cache')->pluck('key')->toArray();

    // Only the specified key should be cleared
    expect($remainingKeys)->not->toContain($laravelPrefix.'fulfil_sandbox_active_customers');
    expect($remainingKeys)->toContain($laravelPrefix.'fulfil_sandbox_products');
});

test('clearCachePattern clears matching keys without affecting others', function () {
    // Switch to database cache driver for this test
    config(['cache.default' => 'database']);

    $laravelPrefix = config('cache.prefix', '');

    DB::table('cache')->insert([
        ['key' => $laravelPrefix.'fulfil_sandbox_invoices_abc123', 'value' => serialize('data1'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_sandbox_invoices_def456', 'value' => serialize('data2'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_sandbox_active_customers', 'value' => serialize('data3'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'unrelated_key', 'value' => serialize('data4'), 'expiration' => time() + 3600],
    ]);

    $service = new FulfilService('sandbox');
    $service->clearCachePattern('invoices_');

    $remainingKeys = DB::table('cache')->pluck('key')->toArray();

    // Invoice keys should be deleted
    expect($remainingKeys)->not->toContain($laravelPrefix.'fulfil_sandbox_invoices_abc123');
    expect($remainingKeys)->not->toContain($laravelPrefix.'fulfil_sandbox_invoices_def456');

    // Other keys should remain
    expect($remainingKeys)->toContain($laravelPrefix.'fulfil_sandbox_active_customers');
    expect($remainingKeys)->toContain($laravelPrefix.'unrelated_key');
});

test('toggling test mode clears fulfil cache for both environments', function () {
    // Switch to database cache driver for this test
    config(['cache.default' => 'database']);

    $laravelPrefix = config('cache.prefix', '');

    DB::table('cache')->insert([
        ['key' => $laravelPrefix.'fulfil_sandbox_active_customers', 'value' => serialize('sandbox_data'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'fulfil_production_active_customers', 'value' => serialize('prod_data'), 'expiration' => time() + 3600],
        ['key' => $laravelPrefix.'unrelated_key', 'value' => serialize('other_data'), 'expiration' => time() + 3600],
    ]);

    $testMode = new TestModeService;
    $testMode->enable();

    // Both Fulfil environment caches should be cleared
    $remainingKeys = DB::table('cache')->pluck('key')->toArray();
    $fulfilKeys = array_filter($remainingKeys, fn ($key) => str_contains($key, 'fulfil_'));
    expect($fulfilKeys)->toBeEmpty();

    // Unrelated keys should remain (excluding the setting_ar_test_mode key that enable() creates)
    expect($remainingKeys)->toContain($laravelPrefix.'unrelated_key');
});
