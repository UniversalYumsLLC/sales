<?php

use App\Models\FulfilBrokerContact;
use App\Models\FulfilUncategorizedContact;
use App\Models\LocalCustomerMetadata;
use App\Models\User;
use App\Services\FulfilService;

beforeEach(function () {
    $this->mock(FulfilService::class, function ($mock) {
        $mock->shouldReceive('getActiveCustomers')->andReturn([
            ['id' => 1, 'name' => 'Test Customer', 'buyers' => [], 'accounts_payable' => [], 'other' => []],
        ]);
        $mock->shouldReceive('getSalesOrders')->andReturn([
            ['id' => 10, 'party_id' => 1, 'state' => 'done', 'total_amount' => 1000, 'sale_date' => now()->subDays(30)->toDateString(), 'shipping_end_date' => now()->subDays(25)->toDateString(), 'shipment_effective_date' => now()->subDays(25)->toDateString(), 'lines' => [], 'reference' => 'PO-001'],
        ]);
        $mock->shouldReceive('getInvoices')->andReturn([]);
        $mock->shouldReceive('getCustomerArSettings')->andReturn([
            'edi' => false, 'consolidated_invoicing' => false,
            'requires_customer_skus' => false, 'invoice_discount' => null,
        ]);
        $mock->shouldReceive('getAllPriceLists')->andReturn([]);
        $mock->shouldReceive('getAllPaymentTerms')->andReturn([]);
        $mock->shouldReceive('getShippingTermsCategories')->andReturn([]);
        $mock->shouldReceive('getProducts')->andReturn([]);
        $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
        $mock->shouldReceive('updateCustomerArSettings')->andReturn(null);
    });
});

test('index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});

test('unauthenticated users get redirected from index', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('show page loads for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/customers/1');

    $response->assertStatus(200);
});

test('unauthenticated users get redirected from show', function () {
    $response = $this->get('/customers/1');

    $response->assertRedirect('/login');
});

test('company URLs can be updated', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);

    $response = $this->actingAs($user)->put('/customers/100/company-urls', [
        'company_urls' => ['example.com', 'https://www.acme.org'],
    ]);

    $response->assertRedirect();

    $metadata = LocalCustomerMetadata::find(100);
    expect($metadata->company_urls)->toContain('example.com')
        ->and($metadata->company_urls)->toContain('acme.org');
});

test('create local contact works', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);

    $response = $this->actingAs($user)->postJson('/customers/100/contacts', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Contact created successfully']);

    expect(FulfilUncategorizedContact::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('update local contact works', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);
    $contact = FulfilUncategorizedContact::create([
        'fulfil_party_id' => 100,
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($user)->putJson("/customers/100/contacts/{$contact->id}", [
        'name' => 'New Name',
    ]);

    $response->assertStatus(200);
    expect($contact->fresh()->name)->toBe('New Name');
});

test('delete local contact works', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);
    $contact = FulfilUncategorizedContact::create([
        'fulfil_party_id' => 100,
        'name' => 'Delete Me',
        'email' => 'delete@example.com',
    ]);

    $response = $this->actingAs($user)->deleteJson("/customers/100/contacts/{$contact->id}");

    $response->assertStatus(200);
    expect(FulfilUncategorizedContact::find($contact->id))->toBeNull();
});

test('broker settings can be updated', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);

    $response = $this->actingAs($user)->putJson('/customers/100/broker', [
        'broker' => true,
        'broker_commission' => 5.5,
        'broker_company_name' => 'Test Broker Co',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Broker settings updated successfully']);

    $metadata = LocalCustomerMetadata::find(100);
    expect($metadata->broker)->toBeTrue()
        ->and($metadata->broker_commission)->toBe('5.50')
        ->and($metadata->broker_company_name)->toBe('Test Broker Co');
});

test('customer type can be changed to distributor', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create([
        'fulfil_party_id' => 100,
        'customer_type' => 'retailer',
        'broker' => false,
    ]);

    $response = $this->actingAs($user)->patchJson('/customers/100/customer-type', [
        'customer_type' => 'distributor',
    ]);

    $response->assertStatus(200);
    expect(LocalCustomerMetadata::find(100)->customer_type)->toBe('distributor');
});

test('customer type cannot change to distributor when broker data exists', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create([
        'fulfil_party_id' => 100,
        'customer_type' => 'retailer',
        'broker' => true,
        'broker_company_name' => 'Some Broker',
    ]);

    $response = $this->actingAs($user)->patchJson('/customers/100/customer-type', [
        'customer_type' => 'distributor',
    ]);

    $response->assertStatus(422);
});

test('categorize contact works', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);
    $contact = FulfilUncategorizedContact::create([
        'fulfil_party_id' => 100,
        'name' => 'Uncategorized Contact',
        'email' => 'contact@example.com',
        'type' => null,
    ]);

    $response = $this->actingAs($user)->patchJson("/customers/100/contacts/{$contact->id}/categorize", [
        'type' => 'buyer',
    ]);

    $response->assertStatus(200);
    expect($contact->fresh()->type)->toBe('buyer');
});

test('cannot categorize already categorized contact', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);
    $contact = FulfilUncategorizedContact::create([
        'fulfil_party_id' => 100,
        'name' => 'Already Buyer',
        'email' => 'buyer@example.com',
        'type' => 'buyer',
    ]);

    $response = $this->actingAs($user)->patchJson("/customers/100/contacts/{$contact->id}/categorize", [
        'type' => 'other',
    ]);

    $response->assertStatus(422);
});

test('create broker contact works', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);

    $response = $this->actingAs($user)->postJson('/customers/100/broker-contacts', [
        'name' => 'Broker Person',
        'email' => 'broker@brokerco.com',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Broker contact created successfully']);

    expect(FulfilBrokerContact::where('email', 'broker@brokerco.com')->exists())->toBeTrue();
});

test('duplicate broker contact email is rejected', function () {
    $user = User::factory()->create();
    LocalCustomerMetadata::factory()->create(['fulfil_party_id' => 100]);
    FulfilBrokerContact::create([
        'fulfil_party_id' => 100,
        'name' => 'Existing',
        'email' => 'broker@brokerco.com',
    ]);

    $response = $this->actingAs($user)->postJson('/customers/100/broker-contacts', [
        'name' => 'Duplicate',
        'email' => 'broker@brokerco.com',
    ]);

    $response->assertStatus(422);
});

test('AR settings can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/customers/100/ar-settings', [
        'edi' => true,
        'consolidated_invoicing' => false,
        'requires_customer_skus' => false,
        'invoice_discount' => null,
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'AR settings updated successfully']);
});
