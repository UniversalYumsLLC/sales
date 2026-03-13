<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 'uncategorized' to the type ENUM for prospect_contacts.
     * This is needed for email discovery to create contacts without a known category.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('buyer', 'accounts_payable', 'logistics', 'uncategorized') DEFAULT 'buyer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any 'uncategorized' contacts to 'buyer'
        DB::statement("UPDATE prospect_contacts SET type = 'buyer' WHERE type = 'uncategorized'");
        DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('buyer', 'accounts_payable', 'logistics') DEFAULT 'buyer'");
    }
};
