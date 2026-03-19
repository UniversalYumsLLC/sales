<?php

use App\Models\Prospect;

test('getEmailDomains extracts domains from URLs', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['https://www.example.com', 'acme.org'],
    ]);

    $domains = $prospect->getEmailDomains();

    expect($domains)->toContain('example.com')
        ->and($domains)->toContain('acme.org');
});

test('getEmailDomains removes www prefix', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['www.example.com'],
    ]);

    $domains = $prospect->getEmailDomains();

    expect($domains)->toContain('example.com')
        ->and($domains)->not->toContain('www.example.com');
});

test('getEmailDomains returns empty array when no URLs', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => null,
    ]);

    expect($prospect->getEmailDomains())->toBe([]);
});

test('getEmailDomains deduplicates domains', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['https://www.example.com', 'example.com'],
    ]);

    $domains = $prospect->getEmailDomains();

    expect(array_count_values($domains)['example.com'])->toBe(1);
});

test('matchesEmailDomain returns true for matching email', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['example.com'],
    ]);

    expect($prospect->matchesEmailDomain('john@example.com'))->toBeTrue();
});

test('matchesEmailDomain returns false for non-matching email', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['example.com'],
    ]);

    expect($prospect->matchesEmailDomain('john@other.com'))->toBeFalse();
});

test('matchesEmailDomain returns false for invalid email', function () {
    $prospect = Prospect::factory()->create([
        'company_urls' => ['example.com'],
    ]);

    expect($prospect->matchesEmailDomain('notanemail'))->toBeFalse();
});

test('status constants are correct', function () {
    expect(Prospect::STATUS_TARGET)->toBe('target')
        ->and(Prospect::STATUS_CONTACTED)->toBe('contacted')
        ->and(Prospect::STATUS_ENGAGED)->toBe('engaged')
        ->and(Prospect::STATUS_DORMANT)->toBe('dormant');
});

test('getStatuses returns all statuses with labels and descriptions', function () {
    $statuses = Prospect::getStatuses();

    expect($statuses)->toHaveKeys([
        Prospect::STATUS_TARGET,
        Prospect::STATUS_CONTACTED,
        Prospect::STATUS_ENGAGED,
        Prospect::STATUS_DORMANT,
    ]);

    foreach ($statuses as $status) {
        expect($status)->toHaveKeys(['label', 'description']);
    }
});

test('scopeNotDormant excludes dormant prospects', function () {
    Prospect::factory()->create(['status' => Prospect::STATUS_TARGET]);
    Prospect::factory()->create(['status' => Prospect::STATUS_CONTACTED]);
    Prospect::factory()->dormant()->create();

    $results = Prospect::notDormant()->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('status')->toArray())->not->toContain(Prospect::STATUS_DORMANT);
});

test('addCompanyUrl adds new URL', function () {
    $prospect = Prospect::factory()->create(['company_urls' => ['existing.com']]);

    $result = $prospect->addCompanyUrl('newdomain.com');

    expect($result)->toBeTrue()
        ->and($prospect->fresh()->company_urls)->toContain('newdomain.com')
        ->and($prospect->fresh()->company_urls)->toContain('existing.com');
});

test('addCompanyUrl rejects duplicate URL', function () {
    $prospect = Prospect::factory()->create(['company_urls' => ['existing.com']]);

    $result = $prospect->addCompanyUrl('existing.com');

    expect($result)->toBeFalse()
        ->and($prospect->fresh()->company_urls)->toHaveCount(1);
});
