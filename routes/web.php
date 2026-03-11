<?php

use App\Http\Controllers\ActiveCustomersController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Active Customers (home page)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [ActiveCustomersController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}', [ActiveCustomersController::class, 'show'])->name('customers.show');

    // Accounts Receivable
    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index'])->name('ar.index');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

// Admin routes
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::post('/admin/users/invite', [UserManagementController::class, 'invite'])->name('admin.users.invite');
    Route::delete('/admin/users/invite/{invite}', [UserManagementController::class, 'cancelInvite'])->name('admin.users.invite.cancel');
    Route::patch('/admin/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('admin.users.role');
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
});

require __DIR__.'/auth.php';
