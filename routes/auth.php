<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes - OAuth Only
|--------------------------------------------------------------------------
|
| This application uses Google OAuth as the exclusive authentication method.
| Password-based authentication has been disabled.
|
*/

Route::middleware('guest')->group(function () {
    // Login page - displays Google OAuth button
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // OAuth routes - Google authentication flow
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
