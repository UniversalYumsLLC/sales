<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'broker' to prospect_contacts type and fix the SQLite CHECK constraint.
     *
     * Previous migrations only updated the MySQL ENUM, leaving SQLite's CHECK
     * stuck on the original three values ('buyer', 'accounts_payable', 'logistics').
     * This brings both drivers in sync with all five valid types.
     */
    public function up(): void
    {
        $allTypes = ['buyer', 'accounts_payable', 'other', 'uncategorized', 'broker'];

        if (DB::connection()->getDriverName() === 'mysql') {
            $enum = implode("','", $allTypes);
            DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('{$enum}') NOT NULL DEFAULT 'buyer'");
        } else {
            // SQLite: recreate column to update the CHECK constraint
            Schema::table('prospect_contacts', function (Blueprint $table) use ($allTypes) {
                $table->enum('type', $allTypes)->default('buyer')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $previousTypes = ['buyer', 'accounts_payable', 'other', 'uncategorized'];

        // Move broker contacts to uncategorized before shrinking the constraint
        DB::table('prospect_contacts')
            ->where('type', 'broker')
            ->update(['type' => 'uncategorized']);

        if (DB::connection()->getDriverName() === 'mysql') {
            $enum = implode("','", $previousTypes);
            DB::statement("ALTER TABLE prospect_contacts MODIFY COLUMN type ENUM('{$enum}') NOT NULL DEFAULT 'buyer'");
        } else {
            Schema::table('prospect_contacts', function (Blueprint $table) use ($previousTypes) {
                $table->enum('type', $previousTypes)->default('buyer')->change();
            });
        }
    }
};
