<?php

use App\Models\User;
use App\Services\FulfilService;

test('index loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->mock(FulfilService::class, function ($mock) {
        $mock->shouldReceive('getActiveCustomers')->andReturn([]);
        $mock->shouldReceive('getInvoices')->andReturn([]);
        $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
    });

    $response = $this->actingAs($user)->get('/accounts-receivable');

    $response->assertStatus(200);
});

test('index redirects unauthenticated users', function () {
    $response = $this->get('/accounts-receivable');

    $response->assertRedirect('/login');
});

test('index passes customer and totals data', function () {
    $user = User::factory()->create();

    $this->mock(FulfilService::class, function ($mock) {
        $mock->shouldReceive('getActiveCustomers')->andReturn([
            ['id' => 1, 'name' => 'Customer A', 'accounts_payable' => []],
        ]);
        $mock->shouldReceive('getInvoices')->andReturn([
            [
                'id' => 100,
                'number' => 'INV-001',
                'party_id' => 1,
                'total_amount' => 1000,
                'balance' => 500,
                'due_date' => now()->subDays(5)->toDateString(),
                'state' => 'posted',
            ],
        ]);
        $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
    });

    $response = $this->actingAs($user)->get('/accounts-receivable');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('AccountsReceivable/Index')
            ->has('customers')
            ->has('totals')
        );
});
