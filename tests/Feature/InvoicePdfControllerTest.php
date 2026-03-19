<?php

use App\Models\User;
use App\Services\ArAutomationService;
use App\Services\InvoicePdfService;

test('download route requires auth', function () {
    $response = $this->get('/invoices/123/pdf/download');

    $response->assertRedirect('/login');
});

test('regenerate route requires auth', function () {
    $response = $this->post('/invoices/123/pdf/regenerate');

    $response->assertRedirect('/login');
});

test('download route accessible when authenticated', function () {
    $user = User::factory()->create();

    $this->mock(InvoicePdfService::class, function ($mock) {
        $mock->shouldReceive('getOrGenerate')->with(123)->andReturn('invoices/INV-123.pdf');
        $mock->shouldReceive('getFullPath')->andReturn('/tmp/test.pdf');
    });

    // Create a temp file to satisfy the download response
    file_put_contents('/tmp/test.pdf', 'fake pdf content');

    $response = $this->actingAs($user)->get('/invoices/123/pdf/download');

    // Clean up
    @unlink('/tmp/test.pdf');

    $response->assertStatus(200);
});

test('regenerate route accessible when authenticated', function () {
    $user = User::factory()->create();

    $this->mock(InvoicePdfService::class, function ($mock) {
        $mock->shouldReceive('regenerate')->with(123)->andReturn('invoices/INV-123.pdf');
    });

    $response = $this->actingAs($user)->postJson('/invoices/123/pdf/regenerate');

    $response->assertStatus(200)
        ->assertJson(['message' => 'PDF regenerated successfully']);
});

test('resend email validates email_type input', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/invoices/123/resend-email', [
        'email_type' => 'invalid_type',
    ]);

    $response->assertStatus(422);
});

test('resend email accepts valid email types', function () {
    $user = User::factory()->create();

    $this->mock(ArAutomationService::class, function ($mock) {
        $mock->shouldReceive('resendEmail')->andReturn(true);
    });

    $response = $this->actingAs($user)->postJson('/invoices/123/resend-email', [
        'email_type' => 'initial_invoice',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Email sent successfully']);
});

test('resend email requires auth', function () {
    $response = $this->postJson('/invoices/123/resend-email', [
        'email_type' => 'initial_invoice',
    ]);

    $response->assertStatus(401);
});
