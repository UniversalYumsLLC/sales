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
        Schema::table('fulfil_customer_metadata', function (Blueprint $table) {
            $table->boolean('broker')->default(false)->after('company_urls');
            $table->decimal('broker_commission', 5, 2)->nullable()->after('broker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fulfil_customer_metadata', function (Blueprint $table) {
            $table->dropColumn(['broker', 'broker_commission']);
        });
    }
};
