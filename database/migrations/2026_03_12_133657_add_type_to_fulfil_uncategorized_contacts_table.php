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
        Schema::table('fulfil_uncategorized_contacts', function (Blueprint $table) {
            // Type: null = uncategorized, or 'buyer', 'accounts_payable', 'logistics'
            $table->string('type')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fulfil_uncategorized_contacts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
