<?php

use App\Models\User;

test('admin can manage customers', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    expect($user->canManageCustomers())->toBeTrue();
});

test('salesperson can manage customers', function () {
    $user = User::factory()->create(['role' => User::ROLE_SALESPERSON]);

    expect($user->canManageCustomers())->toBeTrue();
});

test('basic user cannot manage customers', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    expect($user->canManageCustomers())->toBeFalse();
});
