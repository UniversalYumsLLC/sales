<?php

use App\Models\Setting;
use App\Services\TestModeService;
use Illuminate\Support\Facades\Cache;

test('filterEmails passes all emails in production mode', function () {
    Setting::set('ar_test_mode', false);

    $service = new TestModeService;
    $emails = ['customer@external.com', 'buyer@store.com'];

    expect($service->filterEmails($emails))->toBe($emails);
});

test('filterEmails redirects external emails in test mode', function () {
    Setting::set('ar_test_mode', true);

    $service = new TestModeService;
    $emails = ['customer@external.com', 'staff@universalyums.com'];

    $filtered = $service->filterEmails($emails);

    expect($filtered)->toContain('staff@universalyums.com')
        ->and($filtered)->toContain('accountsreceivable@universalyums.com')
        ->and($filtered)->not->toContain('customer@external.com');
});

test('filterEmails only allows universalyums.com in test mode', function () {
    Setting::set('ar_test_mode', true);

    $service = new TestModeService;
    $emails = ['customer@external.com', 'buyer@store.com'];

    $filtered = $service->filterEmails($emails);

    // External emails should be replaced by redirect email
    expect($filtered)->toBe(['accountsreceivable@universalyums.com']);
});

test('getFulfilEnvironment returns sandbox in test mode', function () {
    Setting::set('ar_test_mode', true);

    $service = new TestModeService;

    expect($service->getFulfilEnvironment())->toBe('sandbox');
});

test('getFulfilEnvironment returns production when test mode disabled', function () {
    Setting::set('ar_test_mode', false);

    $service = new TestModeService;

    expect($service->getFulfilEnvironment())->toBe('production');
});

test('canSendEmailTo allows any email in production mode', function () {
    Setting::set('ar_test_mode', false);

    $service = new TestModeService;

    expect($service->canSendEmailTo('anyone@external.com'))->toBeTrue();
});

test('canSendEmailTo blocks external emails in test mode', function () {
    Setting::set('ar_test_mode', true);

    $service = new TestModeService;

    expect($service->canSendEmailTo('customer@external.com'))->toBeFalse()
        ->and($service->canSendEmailTo('staff@universalyums.com'))->toBeTrue();
});

test('isEnabled returns the correct test mode state', function () {
    Setting::set('ar_test_mode', false);
    $service = new TestModeService;
    expect($service->isEnabled())->toBeFalse();

    Setting::set('ar_test_mode', true);
    // Setting::set() already clears cache internally
    expect($service->isEnabled())->toBeTrue();
});
