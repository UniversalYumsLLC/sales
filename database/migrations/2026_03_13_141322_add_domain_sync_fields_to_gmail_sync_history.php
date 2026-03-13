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
        Schema::table('gmail_sync_history', function (Blueprint $table) {
            // Sync type: 'full' for complete user sync, 'domain' for entity-specific sync
            $table->string('sync_type', 20)->default('full')->after('user_id');

            // Entity that triggered the sync (for domain syncs)
            $table->string('entity_type', 20)->nullable()->after('sync_type'); // 'prospect' or 'customer'
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');

            // Domains that were synced (for domain syncs)
            $table->json('domains')->nullable()->after('entity_id');

            // Index for looking up syncs by entity
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmail_sync_history', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropColumn(['sync_type', 'entity_type', 'entity_id', 'domains']);
        });
    }
};
