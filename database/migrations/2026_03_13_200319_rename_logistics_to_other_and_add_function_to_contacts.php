<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames 'logistics' type to 'other' and adds 'function' field for contact role description.
     */
    public function up(): void
    {
        // Step 1: Add 'function' column first
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->string('function', 100)->nullable()->after('type');
        });

        // Step 2: For existing 'logistics' records, set function to 'Logistics' before renaming
        DB::table('prospect_contacts')
            ->where('type', 'logistics')
            ->update(['function' => 'Logistics']);

        // Step 3: Update existing 'logistics' records to 'other'
        DB::table('prospect_contacts')
            ->where('type', 'logistics')
            ->update(['type' => 'other']);

        // Step 4: For MySQL, alter ENUM to replace 'logistics' with 'other'
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('buyer', 'accounts_payable', 'other', 'uncategorized') DEFAULT 'buyer'");
        }
        // SQLite doesn't have ENUM, so no modification needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'other' back to 'logistics'
        DB::table('prospect_contacts')
            ->where('type', 'other')
            ->update(['type' => 'logistics']);

        // For MySQL, revert ENUM
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('buyer', 'accounts_payable', 'logistics', 'uncategorized') DEFAULT 'buyer'");
        }

        // Remove function column
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->dropColumn('function');
        });
    }
};
