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
        Schema::table('emails', function (Blueprint $table) {
            // Add fulfil_party_id for customer emails (nullable - either prospect_id OR fulfil_party_id will be set)
            $table->unsignedBigInteger('fulfil_party_id')->nullable()->after('prospect_id')->index();

            // Make prospect_id nullable (it was required before, now it's optional if fulfil_party_id is set)
            $table->unsignedBigInteger('prospect_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('fulfil_party_id');
        });
    }
};
