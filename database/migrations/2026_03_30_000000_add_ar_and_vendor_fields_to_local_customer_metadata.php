<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('local_customer_metadata', function (Blueprint $table) {
            $table->boolean('ar_edi')->default(false)->after('broker_company_name');
            $table->boolean('ar_consolidated_invoicing')->default(false)->after('ar_edi');
            $table->boolean('ar_requires_customer_skus')->default(false)->after('ar_consolidated_invoicing');
            $table->string('vendor_guide', 500)->nullable()->after('ar_requires_customer_skus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_customer_metadata', function (Blueprint $table) {
            $table->dropColumn([
                'ar_edi',
                'ar_consolidated_invoicing',
                'ar_requires_customer_skus',
                'vendor_guide',
            ]);
        });
    }
};
