<?php

use App\Support\CompanyFields;

test('sanitizeInput casts boolean string "true" to true', function () {
    $result = CompanyFields::sanitizeInput(['broker' => 'true']);

    expect($result['broker'])->toBeTrue();
});

test('sanitizeInput casts boolean string "false" to false', function () {
    $result = CompanyFields::sanitizeInput(['broker' => 'false']);

    expect($result['broker'])->toBeFalse();
});

test('sanitizeInput casts "1" and "0" to booleans', function () {
    $result = CompanyFields::sanitizeInput([
        'broker' => '1',
        'ar_edi' => '0',
    ]);

    expect($result['broker'])->toBeTrue()
        ->and($result['ar_edi'])->toBeFalse();
});

test('sanitizeInput returns null for non-boolean strings', function () {
    $result = CompanyFields::sanitizeInput(['broker' => 'notabool']);

    expect($result['broker'])->toBeNull();
});

test('sanitizeInput preserves actual booleans', function () {
    $result = CompanyFields::sanitizeInput([
        'broker' => true,
        'ar_edi' => false,
    ]);

    expect($result['broker'])->toBeTrue()
        ->and($result['ar_edi'])->toBeFalse();
});

test('sanitizeInput casts all boolean fields', function () {
    $result = CompanyFields::sanitizeInput([
        'broker' => 'true',
        'ar_edi' => 'false',
        'ar_consolidated_invoicing' => '1',
        'ar_requires_customer_skus' => '0',
    ]);

    expect($result['broker'])->toBeTrue()
        ->and($result['ar_edi'])->toBeFalse()
        ->and($result['ar_consolidated_invoicing'])->toBeTrue()
        ->and($result['ar_requires_customer_skus'])->toBeFalse();
});

test('sanitizeInput does not touch missing boolean fields', function () {
    $result = CompanyFields::sanitizeInput(['company_name' => 'Test']);

    expect($result)->not->toHaveKey('broker')
        ->and($result['company_name'])->toBe('Test');
});

test('sanitizeInput strips blank contacts', function () {
    $result = CompanyFields::sanitizeInput([
        'buyers' => [
            ['name' => 'John', 'email' => 'john@test.com'],
            ['name' => '', 'email' => ''],
            ['name' => '  ', 'email' => ''],
        ],
    ]);

    expect($result['buyers'])->toHaveCount(1)
        ->and($result['buyers'][0]['name'])->toBe('John');
});
