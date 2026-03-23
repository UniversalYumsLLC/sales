<?php

use App\Models\User;
use App\Services\FulfilService;

test('admin can access /admin/users', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertStatus(200);
});

test('admin can access /admin/settings', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/settings');

    $response->assertStatus(200);
});

test('admin can access /admin/email-templates', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin/email-templates');

    $response->assertStatus(200);
});

test('admin can access /admin/email-log', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(FulfilService::class, function ($mock) {
        $mock->shouldReceive('getActiveCustomers')->andReturn([]);
        $mock->shouldReceive('getEnvironment')->andReturn('sandbox');
    });

    $response = $this->actingAs($admin)->get('/admin/email-log');

    $response->assertStatus(200);
});

test('salesperson gets 403 on /admin/users', function () {
    $user = User::factory()->salesperson()->create();

    $response = $this->actingAs($user)->get('/admin/users');

    $response->assertStatus(403);
});

test('salesperson gets 403 on /admin/settings', function () {
    $user = User::factory()->salesperson()->create();

    $response = $this->actingAs($user)->get('/admin/settings');

    $response->assertStatus(403);
});

test('salesperson gets 403 on /admin/email-templates', function () {
    $user = User::factory()->salesperson()->create();

    $response = $this->actingAs($user)->get('/admin/email-templates');

    $response->assertStatus(403);
});

test('salesperson gets 403 on /admin/email-log', function () {
    $user = User::factory()->salesperson()->create();

    $response = $this->actingAs($user)->get('/admin/email-log');

    $response->assertStatus(403);
});

test('basic user gets 403 on /admin/users', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/admin/users');

    $response->assertStatus(403);
});

test('basic user gets 403 on /admin/settings', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/admin/settings');

    $response->assertStatus(403);
});

test('basic user (accounts receivable) can access /admin/email-templates', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/admin/email-templates');

    $response->assertStatus(200);
});

test('basic user gets 403 on /admin/email-log', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    $response = $this->actingAs($user)->get('/admin/email-log');

    $response->assertStatus(403);
});

test('unauthenticated user gets redirected from admin routes', function () {
    $this->get('/admin/users')->assertRedirect('/login');
    $this->get('/admin/settings')->assertRedirect('/login');
    $this->get('/admin/email-templates')->assertRedirect('/login');
    $this->get('/admin/email-log')->assertRedirect('/login');
});
