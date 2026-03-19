<?php

use App\Models\User;
use App\Models\UserGmailToken;
use App\Services\GmailService;

test('index page accessible for salesperson', function () {
    $user = User::factory()->salesperson()->create();

    $response = $this->actingAs($user)->get('/gmail');

    $response->assertStatus(200);
});

test('index page accessible for admin', function () {
    $user = User::factory()->admin()->create();

    $response = $this->actingAs($user)->get('/gmail');

    $response->assertStatus(200);
});

test('index page forbidden for basic user role', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/gmail');

    $response->assertStatus(403);
});

test('connect generates OAuth redirect for salesperson', function () {
    $user = User::factory()->salesperson()->create();

    $this->mock(GmailService::class, function ($mock) {
        $mock->shouldReceive('getAuthorizationUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?state=test');
    });

    $response = $this->actingAs($user)->get('/gmail/connect');

    $response->assertRedirect();
});

test('connect forbidden for basic user', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/gmail/connect');

    $response->assertStatus(403);
});

test('disconnect calls service and redirects', function () {
    $user = User::factory()->salesperson()->create();
    UserGmailToken::factory()->create(['user_id' => $user->id]);

    $this->mock(GmailService::class, function ($mock) {
        $mock->shouldReceive('disconnect')->once();
    });

    $response = $this->actingAs($user)->post('/gmail/disconnect');

    $response->assertRedirect(route('gmail.index'))
        ->assertSessionHas('success');
});

test('fullSyncAll requires admin', function () {
    $salesperson = User::factory()->salesperson()->create();

    $response = $this->actingAs($salesperson)->post('/gmail/full-sync-all');

    $response->assertStatus(403);
});

test('fullSyncAll works for admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post('/gmail/full-sync-all');

    $response->assertRedirect(route('gmail.index'));
});

test('sync requires gmail connection', function () {
    $user = User::factory()->salesperson()->create();

    $this->mock(GmailService::class, function ($mock) {
        $mock->shouldNotReceive('syncEmails');
    });

    $response = $this->actingAs($user)->post('/gmail/sync');

    $response->assertRedirect(route('gmail.index'))
        ->assertSessionHas('error');
});

test('disconnect forbidden for basic user', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->post('/gmail/disconnect');

    $response->assertStatus(403);
});

test('backfill domains requires admin', function () {
    $salesperson = User::factory()->salesperson()->create();

    $response = $this->actingAs($salesperson)->post('/gmail/backfill-domains');

    $response->assertStatus(403);
});
