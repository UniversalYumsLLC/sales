<?php

use App\Models\Invoice;
use App\Models\LocalCustomerMetadata;

test('syncFromFulfil creates new invoice', function () {
    $metadata = LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 12345]);

    $data = [
        'id' => 999,
        'number' => 'INV-001',
        'party_id' => 12345,
        'due_date' => '2026-04-15',
        'create_date' => '2026-03-15',
        'write_date' => '2026-03-15 12:00:00',
        'total_amount' => 1500.00,
        'balance' => 1500.00,
        'state' => 'posted',
    ];

    $invoice = Invoice::syncFromFulfil($data);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->fulfil_id)->toBe(999)
        ->and($invoice->number)->toBe('INV-001')
        ->and($invoice->fulfil_party_id)->toBe(12345)
        ->and($invoice->state)->toBe('posted');
});

test('syncFromFulfil updates existing invoice', function () {
    $metadata = LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 12345]);

    $data = [
        'id' => 999,
        'number' => 'INV-001',
        'party_id' => 12345,
        'due_date' => '2026-04-15',
        'create_date' => '2026-03-15',
        'write_date' => '2026-03-15 12:00:00',
        'total_amount' => 1500.00,
        'balance' => 1500.00,
        'state' => 'posted',
    ];

    $invoice = Invoice::syncFromFulfil($data);

    // Update with new balance
    $updatedData = array_merge($data, [
        'balance' => 500.00,
        'write_date' => '2026-03-16 12:00:00',
    ]);

    $updatedInvoice = Invoice::syncFromFulfil($updatedData);

    expect($updatedInvoice->id)->toBe($invoice->id)
        ->and($updatedInvoice->balance)->toBe('500.00');

    // Should still be only one invoice
    expect(Invoice::query()->count())->toBe(1);
});

test('isOverdue returns true for past-due posted invoice', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(5),
        'state' => Invoice::STATE_POSTED,
    ]);

    expect($invoice->isOverdue())->toBeTrue();
});

test('isOverdue returns false for future-due posted invoice', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->addDays(10),
        'state' => Invoice::STATE_POSTED,
    ]);

    expect($invoice->isOverdue())->toBeFalse();
});

test('isOverdue returns false for paid invoice even if past due', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(5),
        'state' => Invoice::STATE_PAID,
    ]);

    expect($invoice->isOverdue())->toBeFalse();
});

test('daysUntilDue returns positive for future due date', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->addDays(10),
    ]);

    $days = $invoice->daysUntilDue();

    expect($days)->toBeGreaterThan(0);
});

test('daysUntilDue returns negative for past due date', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(10),
    ]);

    $days = $invoice->daysUntilDue();

    expect($days)->toBeLessThan(0);
});

test('daysUntilDue returns null when no due date', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => null,
    ]);

    expect($invoice->daysUntilDue())->toBeNull();
});
