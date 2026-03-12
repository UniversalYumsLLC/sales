<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds customer-related fields to prospects table for data collection
     * in preparation for promotion to active customer.
     */
    public function up(): void
    {
        // Add customer fields to prospects table
        Schema::table('prospects', function (Blueprint $table) {
            $table->integer('discount_percent')->nullable()->after('notes');
            $table->string('payment_terms')->nullable()->after('discount_percent');
            $table->string('shipping_terms')->nullable()->after('payment_terms');
            $table->integer('shelf_life_requirement')->nullable()->after('shipping_terms');
            $table->string('vendor_guide')->nullable()->after('shelf_life_requirement');
        });

        // Add type column to prospect_contacts to support Buyers, AP, and Logistics
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->enum('type', ['buyer', 'accounts_payable', 'logistics'])->default('buyer')->after('prospect_id');
            // Rename email to value to support both email and URL for AP contacts
            $table->renameColumn('email', 'value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->renameColumn('value', 'email');
            $table->dropColumn('type');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'discount_percent',
                'payment_terms',
                'shipping_terms',
                'shelf_life_requirement',
                'vendor_guide',
            ]);
        });
    }
};
