<?php

use App\Models\EmailRecord;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\LocalCustomerMetadata;
use App\Services\ArAutomationService;
use App\Services\ArEmailService;
use App\Services\FulfilService;
use App\Services\InvoicePdfService;
use App\Services\TestModeService;

/**
 * Helper to create the service with mocked dependencies.
 */
function makeArAutomationService(
    ?FulfilService $fulfil = null,
    ?ArEmailService $emailService = null,
    ?InvoicePdfService $pdfService = null,
    ?TestModeService $testMode = null,
): ArAutomationService {
    return new ArAutomationService(
        $fulfil ?? Mockery::mock(FulfilService::class),
        $emailService ?? Mockery::mock(ArEmailService::class),
        $pdfService ?? Mockery::mock(InvoicePdfService::class),
        $testMode ?? Mockery::mock(TestModeService::class)->shouldReceive('isEnabled')->andReturn(false)->getMock(),
    );
}

test('shouldSendOverdueFollowup returns false when no overdue notification was sent', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(15),
        'state' => Invoice::STATE_POSTED,
    ]);

    // No email records exist
    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'shouldSendOverdueFollowup');

    expect($reflection->invoke($service, $invoice, 15))->toBeFalse();
});

test('shouldSendOverdueFollowup returns false when days overdue is less than notification threshold', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDay(),
        'state' => Invoice::STATE_POSTED,
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'shouldSendOverdueFollowup');

    // Less than 1 day overdue (the OVERDUE_NOTIFICATION_DAYS_AFTER constant)
    expect($reflection->invoke($service, $invoice, 0))->toBeFalse();
});

test('shouldSendOverdueFollowup returns true when 7 days since overdue notification', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(10),
        'state' => Invoice::STATE_POSTED,
    ]);

    // Create overdue notification email record 8 days ago
    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'email_type' => EmailTemplate::TYPE_OVERDUE_NOTIFICATION,
        'sent_at' => now()->subDays(8),
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'shouldSendOverdueFollowup');

    expect($reflection->invoke($service, $invoice, 10))->toBeTrue();
});

test('shouldSendOverdueFollowup returns false when less than 7 days since last followup', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(20),
        'state' => Invoice::STATE_POSTED,
    ]);

    // Create overdue notification
    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'email_type' => EmailTemplate::TYPE_OVERDUE_NOTIFICATION,
        'sent_at' => now()->subDays(15),
    ]);

    // Create followup only 3 days ago
    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'email_type' => EmailTemplate::TYPE_OVERDUE_FOLLOWUP,
        'sent_at' => now()->subDays(3),
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'shouldSendOverdueFollowup');

    expect($reflection->invoke($service, $invoice, 20))->toBeFalse();
});

test('shouldSendOverdueFollowup returns true when 7 days since last followup', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'due_date' => now()->subDays(25),
        'state' => Invoice::STATE_POSTED,
    ]);

    // Create overdue notification
    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'email_type' => EmailTemplate::TYPE_OVERDUE_NOTIFICATION,
        'sent_at' => now()->subDays(20),
    ]);

    // Create followup 8 days ago
    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'email_type' => EmailTemplate::TYPE_OVERDUE_FOLLOWUP,
        'sent_at' => now()->subDays(8),
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'shouldSendOverdueFollowup');

    expect($reflection->invoke($service, $invoice, 25))->toBeTrue();
});

test('wasInvoiceModifiedSinceLastEmail returns false when no emails sent', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'wasInvoiceModifiedSinceLastEmail');

    $invoiceData = ['write_date' => now()->toDateTimeString()];

    expect($reflection->invoke($service, $invoice, $invoiceData))->toBeFalse();
});

test('wasInvoiceModifiedSinceLastEmail returns true when Fulfil write_date is after last email', function () {
    $metadata = LocalCustomerMetadata::factory()->create();
    $invoice = Invoice::factory()->create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
    ]);

    EmailRecord::factory()->create([
        'invoice_id' => $invoice->id,
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'sent_at' => now()->subDays(2),
    ]);

    $service = makeArAutomationService();
    $reflection = new ReflectionMethod($service, 'wasInvoiceModifiedSinceLastEmail');

    $invoiceData = ['write_date' => now()->subDay()->toDateTimeString()];

    expect($reflection->invoke($service, $invoice, $invoiceData))->toBeTrue();
});
