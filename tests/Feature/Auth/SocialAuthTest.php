<?php

use App\Models\User;
use App\Models\UserInvite;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Helper to create a mock Socialite user.
 */
function mockSocialiteUser(
    string $email = 'test@universalyums.com',
    string $name = 'Test User',
    string $id = '123456',
    ?string $avatar = 'https://example.com/avatar.jpg',
): SocialiteUser {
    $socialiteUser = new SocialiteUser;
    $socialiteUser->map([
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'avatar' => $avatar,
    ]);

    return $socialiteUser;
}

test('domain-restricted email gets rejected', function () {
    config(['auth.allowed_domains' => 'universalyums.com']);
    config(['auth.allowed_emails' => '']);

    $socialiteUser = mockSocialiteUser(email: 'hacker@evil.com');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('allowed domain email gets through', function () {
    config(['auth.allowed_domains' => 'universalyums.com']);

    $socialiteUser = mockSocialiteUser(email: 'employee@universalyums.com');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('customers.index'));
    $this->assertAuthenticated();
});

test('new user creation gets default user role', function () {
    config(['auth.allowed_domains' => 'universalyums.com']);

    $socialiteUser = mockSocialiteUser(email: 'newguy@universalyums.com', name: 'New Guy', id: '999');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback');

    $user = User::where('email', 'newguy@universalyums.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(User::ROLE_USER)
        ->and($user->google_id)->toBe('999')
        ->and($user->name)->toBe('New Guy');
});

test('new user creation with invite gets correct role', function () {
    config(['auth.allowed_domains' => '']);
    config(['auth.allowed_emails' => '']);

    // Create invite for this email
    $admin = User::factory()->admin()->create();
    UserInvite::factory()->create([
        'email' => 'invited@external.com',
        'role' => User::ROLE_SALESPERSON,
        'invited_by' => $admin->id,
    ]);

    $socialiteUser = mockSocialiteUser(email: 'invited@external.com', name: 'Invited Person', id: '888');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback');

    $user = User::where('email', 'invited@external.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(User::ROLE_SALESPERSON);

    // Invite should be deleted after use
    expect(UserInvite::where('email', 'invited@external.com')->exists())->toBeFalse();
});

test('existing user login updates name and avatar', function () {
    config(['auth.allowed_domains' => 'universalyums.com']);

    $user = User::factory()->create([
        'email' => 'existing@universalyums.com',
        'name' => 'Old Name',
        'avatar' => null,
        'google_id' => '555',
    ]);

    $socialiteUser = mockSocialiteUser(
        email: 'existing@universalyums.com',
        name: 'New Name',
        id: '555',
        avatar: 'https://example.com/newavatar.jpg',
    );

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback');

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->avatar)->toBe('https://example.com/newavatar.jpg');
});

test('invalid provider returns error', function () {
    $response = $this->get('/auth/invalid/callback');

    $response->assertRedirect('/login');
});

test('redirect for valid provider works', function () {
    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $response = $this->get('/auth/google/redirect');

    $response->assertRedirect();
});

test('redirect for invalid provider returns error', function () {
    $response = $this->get('/auth/invalid/redirect');

    $response->assertRedirect('/login');
});
