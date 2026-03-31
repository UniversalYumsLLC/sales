<?php

use App\Models\Prospect;
use App\Models\ProspectContact;
use App\Models\User;
use App\Services\FulfilService;

beforeEach(function () {
    $this->mock(FulfilService::class, function ($mock) {
        $mock->shouldReceive('getProducts')->andReturn([]);
        $mock->shouldReceive('getAllPriceLists')->andReturn([]);
        $mock->shouldReceive('getAllPaymentTerms')->andReturn([]);
        $mock->shouldReceive('getShippingTermsCategories')->andReturn([]);
    });
});

test('index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/prospects');

    $response->assertStatus(200);
});

test('index page redirects unauthenticated users', function () {
    $response = $this->get('/prospects');

    $response->assertRedirect('/login');
});

test('create page loads', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/prospects/create');

    $response->assertStatus(200);
});

test('store creates a new prospect', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prospects', [
        'company_name' => 'New Prospect Inc',
        'buyers' => [
            ['name' => 'John Doe', 'email' => 'john@newprospect.com'],
        ],
    ]);

    $response->assertRedirect(route('prospects.index'));

    $prospect = Prospect::where('company_name', 'New Prospect Inc')->first();
    expect($prospect)->not->toBeNull()
        ->and($prospect->created_by)->toBe($user->id)
        ->and($prospect->status)->toBe(Prospect::STATUS_TARGET);
});

test('store creates contacts with buyer type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/prospects', [
        'company_name' => 'Contact Test Co',
        'buyers' => [
            ['name' => 'Buyer One', 'email' => 'buyer@contacttest.com'],
        ],
    ]);

    $prospect = Prospect::where('company_name', 'Contact Test Co')->first();
    $contacts = ProspectContact::where('prospect_id', $prospect->id)->get();

    expect($contacts)->toHaveCount(1)
        ->and($contacts->first()->type)->toBe(ProspectContact::TYPE_BUYER)
        ->and($contacts->first()->name)->toBe('Buyer One');
});

test('store auto-extracts email domains to company_urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/prospects', [
        'company_name' => 'Domain Extract Co',
        'buyers' => [
            ['name' => 'Person', 'email' => 'person@domainextract.com'],
        ],
    ]);

    $prospect = Prospect::where('company_name', 'Domain Extract Co')->first();
    expect($prospect->company_urls)->toContain('domainextract.com');
});

test('show page loads', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();

    $response = $this->actingAs($user)->get("/prospects/{$prospect->id}");

    $response->assertStatus(200);
});

test('update prospect works', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create(['company_name' => 'Old Name']);

    $response = $this->actingAs($user)->putJson("/prospects/{$prospect->id}", [
        'company_name' => 'New Name',
    ]);

    $response->assertStatus(200);
    expect($prospect->fresh()->company_name)->toBe('New Name');
});

test('status transitions work', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create(['status' => Prospect::STATUS_TARGET]);

    $response = $this->actingAs($user)->patchJson("/prospects/{$prospect->id}/status", [
        'status' => Prospect::STATUS_CONTACTED,
    ]);

    $response->assertStatus(200);
    expect($prospect->fresh()->status)->toBe(Prospect::STATUS_CONTACTED);
});

test('invalid status transition is rejected', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();

    $response = $this->actingAs($user)->patchJson("/prospects/{$prospect->id}/status", [
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422);
});

test('contact categorization works', function () {
    // SQLite ENUM CHECK constraint from migration doesn't support 'uncategorized' type.
    // The MySQL-specific ALTER migration that adds this type is skipped for SQLite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite ENUM CHECK constraint does not include uncategorized type');
    }

    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();
    $contact = ProspectContact::factory()->uncategorized()->create([
        'prospect_id' => $prospect->id,
    ]);

    $response = $this->actingAs($user)->patchJson(
        "/prospects/{$prospect->id}/contacts/{$contact->id}/categorize",
        ['type' => 'buyer']
    );

    $response->assertStatus(200);
    expect($contact->fresh()->type)->toBe(ProspectContact::TYPE_BUYER);
});

test('only uncategorized contacts can be categorized', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();
    $contact = ProspectContact::factory()->buyer()->create([
        'prospect_id' => $prospect->id,
    ]);

    $response = $this->actingAs($user)->patchJson(
        "/prospects/{$prospect->id}/contacts/{$contact->id}/categorize",
        ['type' => 'other']
    );

    $response->assertStatus(422);
});

test('update with buyers replaces existing buyers', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create();
    ProspectContact::factory()->buyer()->create(['prospect_id' => $prospect->id, 'name' => 'Old Buyer']);

    $response = $this->actingAs($user)->putJson("/prospects/{$prospect->id}", [
        'buyers' => [
            ['name' => 'New Buyer', 'value' => 'newbuyer@test.com'],
        ],
    ]);

    $response->assertStatus(200);
    $buyers = ProspectContact::where('prospect_id', $prospect->id)
        ->where('type', ProspectContact::TYPE_BUYER)
        ->get();

    expect($buyers)->toHaveCount(1)
        ->and($buyers->first()->name)->toBe('New Buyer');
});

test('store creates prospect with broker enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prospects', [
        'company_name' => 'Broker Test Co',
        'broker' => 'true',
        'broker_company_name' => 'Brokerage LLC',
        'broker_commission' => '5.5',
        'broker_contacts' => [
            ['name' => 'Broker Person', 'email' => 'broker@brokerage.com'],
        ],
        'buyers' => [
            ['name' => 'Main Buyer', 'email' => 'buyer@brokertest.com'],
        ],
    ]);

    $response->assertRedirect(route('prospects.index'));

    $prospect = Prospect::where('company_name', 'Broker Test Co')->first();
    expect($prospect)->not->toBeNull()
        ->and($prospect->broker)->toBeTrue()
        ->and($prospect->broker_company_name)->toBe('Brokerage LLC')
        ->and((float) $prospect->broker_commission)->toBe(5.5);

    $brokerContacts = ProspectContact::where('prospect_id', $prospect->id)
        ->where('type', ProspectContact::TYPE_BROKER)
        ->get();
    expect($brokerContacts)->toHaveCount(1)
        ->and($brokerContacts->first()->name)->toBe('Broker Person');
});

test('store creates prospect with broker disabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prospects', [
        'company_name' => 'No Broker Co',
        'broker' => 'false',
        'buyers' => [
            ['name' => 'Buyer', 'email' => 'buyer@nobroker.com'],
        ],
    ]);

    $response->assertRedirect(route('prospects.index'));

    $prospect = Prospect::where('company_name', 'No Broker Co')->first();
    expect($prospect)->not->toBeNull()
        ->and($prospect->broker)->toBeFalse();
});

test('store fails when broker is enabled but required broker fields are missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prospects', [
        'company_name' => 'Missing Broker Fields Co',
        'broker' => 'true',
        // Missing broker_company_name, broker_commission, broker_contacts
    ]);

    $response->assertSessionHasErrors(['broker_company_name', 'broker_commission', 'broker_contacts']);
});

test('store validates company name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/prospects', [
        'company_name' => 'X', // too short (min:2)
    ]);

    $response->assertSessionHasErrors('company_name');
});
