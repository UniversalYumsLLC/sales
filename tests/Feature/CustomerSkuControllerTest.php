<?php

use App\Models\CustomerSku;
use App\Models\LocalCustomerMetadata;
use App\Models\User;

beforeEach(function () {
    $this->customerMetadata = LocalCustomerMetadata::create([
        'fulfil_party_id' => 1001,
        'company_urls' => [],
    ]);
});

test('admin can list customer skus', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    CustomerSku::create([
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-001',
        'customer_sku' => 'CUST-001',
    ]);

    $response = $this->actingAs($user)->getJson('/customers/1001/skus');

    $response->assertOk();
    $response->assertJsonCount(1, 'skus');
});

test('salesperson can list customer skus', function () {
    $user = User::factory()->create(['role' => User::ROLE_SALESPERSON]);

    $response = $this->actingAs($user)->getJson('/customers/1001/skus');

    $response->assertOk();
});

test('basic user cannot list customer skus', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->getJson('/customers/1001/skus');

    $response->assertStatus(403);
});

test('admin can create customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($user)->postJson('/customers/1001/skus', [
        'yums_sku' => 'YUMS-002',
        'customer_sku' => 'CUST-002',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('customer_skus', [
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-002',
        'customer_sku' => 'CUST-002',
    ]);
});

test('salesperson can create customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_SALESPERSON]);

    $response = $this->actingAs($user)->postJson('/customers/1001/skus', [
        'yums_sku' => 'YUMS-003',
        'customer_sku' => 'CUST-003',
    ]);

    $response->assertStatus(201);
});

test('basic user cannot create customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->postJson('/customers/1001/skus', [
        'yums_sku' => 'YUMS-004',
        'customer_sku' => 'CUST-004',
    ]);

    $response->assertStatus(403);
});

test('admin can update customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $sku = CustomerSku::create([
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-005',
        'customer_sku' => 'CUST-005',
    ]);

    $response = $this->actingAs($user)->putJson("/customers/1001/skus/{$sku->id}", [
        'customer_sku' => 'CUST-005-UPDATED',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('customer_skus', [
        'id' => $sku->id,
        'customer_sku' => 'CUST-005-UPDATED',
    ]);
});

test('basic user cannot update customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $sku = CustomerSku::create([
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-006',
        'customer_sku' => 'CUST-006',
    ]);

    $response = $this->actingAs($user)->putJson("/customers/1001/skus/{$sku->id}", [
        'customer_sku' => 'CUST-006-UPDATED',
    ]);

    $response->assertStatus(403);
});

test('admin can delete customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $sku = CustomerSku::create([
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-007',
        'customer_sku' => 'CUST-007',
    ]);

    $response = $this->actingAs($user)->deleteJson("/customers/1001/skus/{$sku->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('customer_skus', ['id' => $sku->id]);
});

test('basic user cannot delete customer sku', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $sku = CustomerSku::create([
        'fulfil_party_id' => 1001,
        'yums_sku' => 'YUMS-008',
        'customer_sku' => 'CUST-008',
    ]);

    $response = $this->actingAs($user)->deleteJson("/customers/1001/skus/{$sku->id}");

    $response->assertStatus(403);
});
