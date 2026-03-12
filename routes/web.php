<?php

use App\Http\Controllers\ActiveCustomersController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GmailController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Active Customers (home page)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [ActiveCustomersController::class, 'index'])->name('customers.index');

    // Customer Management (create route must come before {id} wildcard)
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('/api/customers/form-options', [CustomerController::class, 'formOptions'])->name('customers.form-options');

    // Customer detail page (must come after /customers/create)
    Route::get('/customers/{id}', [ActiveCustomersController::class, 'show'])->name('customers.show');
    Route::put('/customers/{id}/company-urls', [ActiveCustomersController::class, 'updateCompanyUrls'])->name('customers.update-company-urls');

    // Prospects (create route must come before {id} wildcard)
    Route::get('/prospects', [ProspectController::class, 'index'])->name('prospects.index');
    Route::get('/prospects/create', [ProspectController::class, 'create'])->name('prospects.create');
    Route::post('/prospects', [ProspectController::class, 'store'])->name('prospects.store');
    Route::patch('/prospects/{id}/status', [ProspectController::class, 'updateStatus'])->name('prospects.update-status');
    Route::get('/prospects/{id}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::put('/prospects/{id}', [ProspectController::class, 'update'])->name('prospects.update');

    // Accounts Receivable
    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index'])->name('ar.index');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Gmail Integration (salesperson only - checked in controller)
    Route::get('/gmail', [GmailController::class, 'index'])->name('gmail.index');
    Route::get('/gmail/connect', [GmailController::class, 'connect'])->name('gmail.connect');
    Route::get('/gmail/callback', [GmailController::class, 'callback'])->name('gmail.callback');
    Route::post('/gmail/disconnect', [GmailController::class, 'disconnect'])->name('gmail.disconnect');
    Route::post('/gmail/sync', [GmailController::class, 'sync'])->name('gmail.sync');
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
