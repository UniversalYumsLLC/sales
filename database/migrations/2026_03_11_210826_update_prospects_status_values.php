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
        // Drop the index first, then the column
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_status_index');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->enum('status', ['target', 'contacted', 'engaged', 'dormant'])
                ->default('target')
                ->after('notes');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->enum('status', ['active', 'converted', 'archived'])
                ->default('active')
                ->after('notes');
            $table->index('status');
        });
    }
};
