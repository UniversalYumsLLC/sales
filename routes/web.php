<?php

use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\ActiveCustomersController;
use App\Http\Controllers\AdminEmailLogController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerSkuController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\GmailController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\UserManagementController;
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
    // Customer emails
    Route::get('/customers/{id}/emails', [ActiveCustomersController::class, 'getEmails'])->name('customers.emails');
    Route::get('/customers/{customerId}/emails/{emailId}', [ActiveCustomersController::class, 'getEmail'])->name('customers.emails.show');
    // Local contact management for customers
    Route::post('/customers/{id}/contacts', [ActiveCustomersController::class, 'createLocalContact'])->name('customers.contacts.create');
    Route::put('/customers/{customerId}/contacts/{contactId}', [ActiveCustomersController::class, 'updateLocalContact'])->name('customers.contacts.update');
    Route::delete('/customers/{customerId}/contacts/{contactId}', [ActiveCustomersController::class, 'deleteLocalContact'])->name('customers.contacts.delete');
    Route::patch('/customers/{customerId}/contacts/{contactId}/categorize', [ActiveCustomersController::class, 'categorizeContact'])->name('customers.contacts.categorize');
    // Broker management for customers
    Route::put('/customers/{id}/broker', [ActiveCustomersController::class, 'updateBroker'])->name('customers.broker.update');
    // AR automation settings
    Route::put('/customers/{id}/ar-settings', [ActiveCustomersController::class, 'updateArSettings'])->name('customers.ar-settings.update');
    Route::post('/customers/{id}/broker-contacts', [ActiveCustomersController::class, 'createBrokerContact'])->name('customers.broker-contacts.create');
    Route::put('/customers/{customerId}/broker-contacts/{contactId}', [ActiveCustomersController::class, 'updateBrokerContact'])->name('customers.broker-contacts.update');
    Route::delete('/customers/{customerId}/broker-contacts/{contactId}', [ActiveCustomersController::class, 'deleteBrokerContact'])->name('customers.broker-contacts.delete');
    // Customer type management
    Route::patch('/customers/{id}/customer-type', [ActiveCustomersController::class, 'updateCustomerType'])->name('customers.update-customer-type');
    // Distributor customer management
    Route::post('/customers/{id}/distributor-customers', [ActiveCustomersController::class, 'createDistributorCustomer'])->name('customers.distributor-customers.create');
    Route::put('/customers/{customerId}/distributor-customers/{distributorCustomerId}', [ActiveCustomersController::class, 'updateDistributorCustomer'])->name('customers.distributor-customers.update');
    Route::delete('/customers/{customerId}/distributor-customers/{distributorCustomerId}', [ActiveCustomersController::class, 'deleteDistributorCustomer'])->name('customers.distributor-customers.delete');
    // Distributor customer contact management
    Route::post('/distributor-customers/{distributorCustomerId}/contacts', [ActiveCustomersController::class, 'createDistributorCustomerContact'])->name('distributor-customers.contacts.create');
    Route::put('/distributor-customers/{distributorCustomerId}/contacts/{contactId}', [ActiveCustomersController::class, 'updateDistributorCustomerContact'])->name('distributor-customers.contacts.update');
    Route::delete('/distributor-customers/{distributorCustomerId}/contacts/{contactId}', [ActiveCustomersController::class, 'deleteDistributorCustomerContact'])->name('distributor-customers.contacts.delete');
    Route::patch('/distributor-customers/{distributorCustomerId}/contacts/{contactId}/categorize', [ActiveCustomersController::class, 'categorizeDistributorCustomerContact'])->name('distributor-customers.contacts.categorize');
    // Customer SKU mapping management
    Route::get('/customers/{id}/skus', [CustomerSkuController::class, 'index'])->name('customers.skus.index');
    Route::post('/customers/{id}/skus', [CustomerSkuController::class, 'store'])->name('customers.skus.store');
    Route::put('/customers/{customerId}/skus/{skuId}', [CustomerSkuController::class, 'update'])->name('customers.skus.update');
    Route::delete('/customers/{customerId}/skus/{skuId}', [CustomerSkuController::class, 'destroy'])->name('customers.skus.destroy');

    // Prospects (create route must come before {id} wildcard)
    Route::get('/prospects', [ProspectController::class, 'index'])->name('prospects.index');
    Route::get('/prospects/create', [ProspectController::class, 'create'])->name('prospects.create');
    Route::post('/prospects', [ProspectController::class, 'store'])->name('prospects.store');
    Route::patch('/prospects/{id}/status', [ProspectController::class, 'updateStatus'])->name('prospects.update-status');
    Route::get('/prospects/{id}', [ProspectController::class, 'show'])->name('prospects.show');
    Route::put('/prospects/{id}', [ProspectController::class, 'update'])->name('prospects.update');
    Route::patch('/prospects/{prospectId}/contacts/{contactId}/categorize', [ProspectController::class, 'categorizeContact'])->name('prospects.contacts.categorize');
    Route::post('/prospects/{id}/promote', [ProspectController::class, 'promote'])->name('prospects.promote');
    // Prospect emails
    Route::get('/prospects/{id}/emails', [ProspectController::class, 'getEmails'])->name('prospects.emails');
    Route::get('/prospects/{prospectId}/emails/{emailId}', [ProspectController::class, 'getEmail'])->name('prospects.emails.show');

    // Accounts Receivable
    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index'])->name('ar.index');

    // Invoice PDF generation
    Route::get('/invoices/{id}/pdf/download', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
    Route::post('/invoices/{id}/pdf/regenerate', [InvoicePdfController::class, 'regenerate'])->name('invoices.pdf.regenerate');

    // AR Automation - resend email
    Route::post('/invoices/{id}/resend-email', [InvoicePdfController::class, 'resendEmail'])->name('invoices.resend-email');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Email Templates (accounts receivable and admin - checked in controller)
    Route::get('/admin/email-templates', [EmailTemplateController::class, 'index'])->name('admin.email-templates');
    Route::put('/admin/email-templates/{key}', [EmailTemplateController::class, 'update'])->name('admin.email-templates.update');

    // Gmail Integration (salesperson and admin - checked in controller)
    Route::get('/gmail', [GmailController::class, 'index'])->name('gmail.index');
    Route::get('/gmail/connect', [GmailController::class, 'connect'])->name('gmail.connect');
    Route::get('/gmail/callback', [GmailController::class, 'callback'])->name('gmail.callback');
    Route::post('/gmail/disconnect', [GmailController::class, 'disconnect'])->name('gmail.disconnect');
    Route::post('/gmail/sync', [GmailController::class, 'sync'])->name('gmail.sync');
    Route::post('/gmail/full-sync', [GmailController::class, 'fullSync'])->name('gmail.full-sync');
    Route::post('/gmail/full-sync-all', [GmailController::class, 'fullSyncAll'])->name('gmail.full-sync-all');
    Route::post('/gmail/backfill-domains', [GmailController::class, 'backfillDomains'])->name('gmail.backfill-domains');
});

// Admin routes
Route::middleware(['auth', 'admin'])->group(function () {
    // User Management
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::post('/admin/users/invite', [UserManagementController::class, 'invite'])->name('admin.users.invite');
    Route::delete('/admin/users/invite/{invite}', [UserManagementController::class, 'cancelInvite'])->name('admin.users.invite.cancel');
    Route::patch('/admin/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('admin.users.role');
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');

    // Admin Settings (Test Mode, etc.)
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings');
    Route::put('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');

    // Email Activity Log
    Route::get('/admin/email-log', [AdminEmailLogController::class, 'index'])->name('admin.email-log');
});

require __DIR__.'/auth.php';
