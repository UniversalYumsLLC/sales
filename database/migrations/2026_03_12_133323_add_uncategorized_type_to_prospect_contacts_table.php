<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: The 'type' column is a varchar, so no schema change is needed.
     * This migration documents the addition of 'uncategorized' as a valid type value.
     * Valid types: buyer, accounts_payable, logistics, uncategorized
     */
    public function up(): void
    {
        // No schema change needed - type column is varchar
        // New type 'uncategorized' is now supported for contact discovery
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema change to revert
    }
};
