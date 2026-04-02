<?php

use App\Models\CustomerSku;
use App\Models\LocalCustomerMetadata;
use App\Services\FulfilService;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Storage;

/**
 * Minimal Fulfil invoice data for InvoicePdfDto::fromFulfil().
 */
function fakeFulfilInvoiceData(int $partyId): array
{
    return [
        'number' => 'INV-99999',
        'invoice_date' => '2026-01-15',
        'due_date' => '2026-02-15',
        'state' => 'posted',
        'reference' => null,
        'total_amount' => 100.00,
        'balance' => 100.00,
        'balance_due' => 100.00,
        'invoice_address' => [
            'name' => 'Test Co',
            'street' => '123 Main St',
            'city' => 'Springfield',
            'subdivision_code' => 'IL',
            'zip' => '62704',
            'country_code' => 'US',
        ],
        'customer_shipments' => [],
        'sales_person_name' => 'Sales Rep',
        'payment_term_name' => 'Net 30',
        'order_number' => 'SO-001',
        'party_id' => $partyId,
        'lines' => [
            [
                'description' => 'Yums Box',
                'product_code' => 'YUMS-001',
                'account_code' => '400',
                'quantity' => 10,
                'unit_price' => 10.00,
                'amount' => 100.00,
            ],
        ],
    ];
}

test('generate reads requires_customer_skus from local metadata, not Fulfil', function () {
    Storage::fake('local');

    $metadata = LocalCustomerMetadata::factory()->create([
        'ar_requires_customer_skus' => true,
    ]);

    // Map the SKU so generation succeeds (no unmapped SKU exception)
    CustomerSku::create([
        'fulfil_party_id' => $metadata->fulfil_party_id,
        'yums_sku' => 'YUMS-001',
        'customer_sku' => 'CUST-001',
    ]);

    $fulfilMock = Mockery::mock(FulfilService::class);
    $fulfilMock->shouldReceive('getInvoiceForPdf')
        ->andReturn(fakeFulfilInvoiceData($metadata->fulfil_party_id));
    // getCustomerArSettings does NOT return requires_customer_skus (matches production)
    $fulfilMock->shouldReceive('getCustomerArSettings')
        ->andReturn(['invoice_discount' => null]);

    $service = new InvoicePdfService($fulfilMock);
    $path = $service->generate(12345);

    expect($path)->toContain('INV-99999.pdf');
    Storage::disk('local')->assertExists($path);
});

test('generate works when local metadata has requires_customer_skus false', function () {
    Storage::fake('local');

    $metadata = LocalCustomerMetadata::factory()->create([
        'ar_requires_customer_skus' => false,
    ]);

    $fulfilMock = Mockery::mock(FulfilService::class);
    $fulfilMock->shouldReceive('getInvoiceForPdf')
        ->andReturn(fakeFulfilInvoiceData($metadata->fulfil_party_id));
    $fulfilMock->shouldReceive('getCustomerArSettings')
        ->andReturn(['invoice_discount' => null]);

    $service = new InvoicePdfService($fulfilMock);
    $path = $service->generate(12345);

    expect($path)->toContain('INV-99999.pdf');
    Storage::disk('local')->assertExists($path);
});

test('generate works when no local metadata exists for customer', function () {
    Storage::fake('local');

    $fulfilMock = Mockery::mock(FulfilService::class);
    $fulfilMock->shouldReceive('getInvoiceForPdf')
        ->andReturn(fakeFulfilInvoiceData(999999));
    $fulfilMock->shouldReceive('getCustomerArSettings')
        ->andReturn(['invoice_discount' => null]);

    $service = new InvoicePdfService($fulfilMock);
    $path = $service->generate(12345);

    expect($path)->toContain('INV-99999.pdf');
    Storage::disk('local')->assertExists($path);
});
