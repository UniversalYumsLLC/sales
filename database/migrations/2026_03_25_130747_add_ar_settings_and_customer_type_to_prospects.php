<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add AR settings, customer_type, and 'active' status to prospects.
     *
     * Keeps the prospect data structure in sync with active customers.
     */
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('customer_type')->nullable()->after('broker_company_name');
            $table->boolean('ar_edi')->default(false)->after('customer_type');
            $table->boolean('ar_consolidated_invoicing')->default(false)->after('ar_edi');
            $table->boolean('ar_requires_customer_skus')->default(false)->after('ar_consolidated_invoicing');
            $table->decimal('ar_invoice_discount', 5, 2)->nullable()->after('ar_requires_customer_skus');
        });

        // Add 'active' to the status enum.
        // The approach matches the existing pattern: drop and recreate.
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_status_index');
        });

        // Preserve existing data
        Schema::table('prospects', function (Blueprint $table) {
            $table->renameColumn('status', 'status_old');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->enum('status', ['target', 'contacted', 'engaged', 'dormant', 'active'])
                ->default('target')
                ->after('notes');
        });

        DB::statement('UPDATE prospects SET status = status_old');

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('status_old');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum (remove 'active')
        DB::statement("UPDATE prospects SET status = 'dormant' WHERE status = 'active'");

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_status_index');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->renameColumn('status', 'status_old');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->enum('status', ['target', 'contacted', 'engaged', 'dormant'])
                ->default('target')
                ->after('notes');
        });

        DB::statement('UPDATE prospects SET status = status_old');

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('status_old');
            $table->index('status');
        });

        // Drop AR settings and customer_type
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type',
                'ar_edi',
                'ar_consolidated_invoicing',
                'ar_requires_customer_skus',
                'ar_invoice_discount',
            ]);
        });
    }
};
