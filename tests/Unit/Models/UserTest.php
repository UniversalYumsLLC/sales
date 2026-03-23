<?php

use App\Models\User;
use App\Models\UserGmailToken;

test('isAdmin returns true for admin role', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

test('isAdmin returns false for non-admin roles', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    expect($user->isAdmin())->toBeFalse();
});

test('isSalesperson returns true for salesperson role', function () {
    $user = User::factory()->salesperson()->create();

    expect($user->isSalesperson())->toBeTrue();
});

test('isSalesperson returns false for non-salesperson roles', function () {
    $user = User::factory()->admin()->create();

    expect($user->isSalesperson())->toBeFalse();
});

test('canAccessGmailIntegration returns true for admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->canAccessGmailIntegration())->toBeTrue();
});

test('canAccessGmailIntegration returns true for salesperson', function () {
    $user = User::factory()->salesperson()->create();

    expect($user->canAccessGmailIntegration())->toBeTrue();
});

test('canAccessGmailIntegration returns false for basic user', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    expect($user->canAccessGmailIntegration())->toBeFalse();
});

test('hasRole works correctly', function () {
    $admin = User::factory()->admin()->create();
    $salesperson = User::factory()->salesperson()->create();
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    expect($admin->hasRole(User::ROLE_ADMIN))->toBeTrue()
        ->and($admin->hasRole(User::ROLE_USER))->toBeFalse()
        ->and($salesperson->hasRole(User::ROLE_SALESPERSON))->toBeTrue()
        ->and($user->hasRole(User::ROLE_USER))->toBeTrue();
});

test('hasGmailConnected returns false when no token exists', function () {
    $user = User::factory()->create();

    expect($user->hasGmailConnected())->toBeFalse();
});

test('hasGmailConnected returns true when token exists', function () {
    $user = User::factory()->create();
    UserGmailToken::factory()->create(['user_id' => $user->id]);

    expect($user->hasGmailConnected())->toBeTrue();
});

test('canEditEmailTemplates returns true for admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->canEditEmailTemplates())->toBeTrue();
});

test('canEditEmailTemplates returns true for accounts receivable (user role)', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    expect($user->canEditEmailTemplates())->toBeTrue();
});

test('canEditEmailTemplates returns false for salesperson', function () {
    $user = User::factory()->salesperson()->create();

    expect($user->canEditEmailTemplates())->toBeFalse();
});

test('getRoles returns all available roles', function () {
    $roles = User::getRoles();

    expect($roles)->toHaveKeys([User::ROLE_USER, User::ROLE_SALESPERSON, User::ROLE_ADMIN])
        ->and($roles)->toHaveCount(3);
});
